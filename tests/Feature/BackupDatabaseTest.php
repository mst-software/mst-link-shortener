<?php

use App\Console\Commands\BackupDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

const BACKUP_TEST_PASSWORD = 'S3cret"pass\\word';

const MYSQLDUMP_HELP = "mysqldump  Ver 8.4.0\n  --column-statistics  Add an ANALYZE TABLE statement to regenerate histogram statistics.";

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 02:30:00');

    $this->workDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'db-backup-test-'.bin2hex(random_bytes(4));
    $this->backupDirectory = $this->workDirectory.DIRECTORY_SEPARATOR.'backups';
    File::ensureDirectoryExists($this->workDirectory);

    config(['database.backups.path' => $this->backupDirectory]);
});

afterEach(function () {
    DB::purge('backup_sqlite');
    $this->sqliteWriter = null;
    gc_collect_cycles();
    File::deleteDirectory($this->workDirectory);
    Carbon::setTestNow();
});

function useSqliteFile(string $path): void
{
    config([
        'database.default' => 'backup_sqlite',
        'database.connections.backup_sqlite' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => ''],
    ]);
}

function useMysql(): void
{
    config([
        'database.default' => 'backup_mysql',
        'database.connections.backup_mysql' => [
            'driver' => 'mysql',
            'host' => 'db.internal',
            'port' => '3306',
            'database' => 'linkcash',
            'username' => 'linkcash',
            'password' => BACKUP_TEST_PASSWORD,
            'unix_socket' => '',
            'prefix' => '',
        ],
    ]);
}

/**
 * The --result-file of a faked mysqldump call.
 */
function dumpTarget(PendingProcess $process): string
{
    $argument = collect($process->command)->first(fn (string $argument): bool => str_starts_with($argument, '--result-file='));

    return substr($argument, strlen('--result-file='));
}

/**
 * A real SQLite database in WAL mode whose rows are still in the WAL file (the writer stays open).
 */
function walDatabase(string $path): PDO
{
    $writer = new PDO('sqlite:'.$path);
    $writer->exec('PRAGMA journal_mode=WAL');
    $writer->exec('PRAGMA wal_autocheckpoint=0');
    $writer->exec('create table links (alias text)');
    $writer->exec("insert into links (alias) values ('abc123'), ('def456')");

    return $writer;
}

it('snapshots the SQLite database including changes still in the WAL file', function () {
    $this->sqliteWriter = walDatabase($this->workDirectory.'/database.sqlite');
    useSqliteFile($this->workDirectory.'/database.sqlite');

    $this->artisan('db:backup')->expectsOutputToContain('Backup written to')->assertSuccessful();

    $backup = new PDO('sqlite:'.$this->backupDirectory.'/backup_sqlite-2026-11-16_023000.sqlite');
    expect((int) $backup->query('select count(*) from links')->fetchColumn())->toBe(2);
    $backup = null;
});

it('makes the backup directory and files readable by the owner only', function () {
    $this->sqliteWriter = walDatabase($this->workDirectory.'/database.sqlite');
    useSqliteFile($this->workDirectory.'/database.sqlite');
    File::ensureDirectoryExists($this->backupDirectory, 0755);
    chmod($this->backupDirectory, 0755);

    $this->artisan('db:backup')->assertSuccessful();

    clearstatcache();
    expect(fileperms($this->backupDirectory) & 0777)->toBe(0700)
        ->and(fileperms($this->backupDirectory.'/backup_sqlite-2026-11-16_023000.sqlite') & 0777)->toBe(0600);
})->skipOnWindows();

it('uses VACUUM INTO only on SQLite versions that have it', function (string $version, bool $supported) {
    expect(BackupDatabase::supportsVacuumInto($version))->toBe($supported);
})->with([
    ['3.26.0', false],
    ['3.27.0', true],
    ['3.45.1', true],
]);

it('keeps only the newest backups and leaves other files alone', function () {
    $this->sqliteWriter = walDatabase($this->workDirectory.'/database.sqlite');
    useSqliteFile($this->workDirectory.'/database.sqlite');
    File::ensureDirectoryExists($this->backupDirectory);

    foreach (['2026-11-13_023000', '2026-11-14_023000', '2026-11-15_023000'] as $stamp) {
        File::put($this->backupDirectory."/backup_sqlite-{$stamp}.sqlite", 'old');
    }
    File::put($this->backupDirectory.'/notes.txt', 'keep me');

    $this->artisan('db:backup', ['--keep' => 2])->expectsOutputToContain('Deleted 2 older backup(s)')->assertSuccessful();

    $names = collect(File::files($this->backupDirectory))->map->getFilename()->sort()->values()->all();
    expect($names)->toBe(['backup_sqlite-2026-11-15_023000.sqlite', 'backup_sqlite-2026-11-16_023000.sqlite', 'notes.txt']);
});

it('rejects a keep count below one', function () {
    $this->artisan('db:backup', ['--keep' => 0])->expectsOutputToContain('at least 1')->assertFailed();
});

it('fails when the SQLite database is not a file', function () {
    useSqliteFile(':memory:');

    $this->artisan('db:backup')->expectsOutputToContain('SQLite database file was not found')->assertFailed();
});

it('dumps MySQL with the credentials in an option file instead of the command line', function () {
    useMysql();
    $options = null;
    $optionsFile = null;

    Process::fake(function (PendingProcess $process) use (&$options, &$optionsFile) {
        if (in_array('--help', $process->command, true)) {
            return Process::result(MYSQLDUMP_HELP);
        }

        $optionsFile = substr($process->command[1], strlen('--defaults-extra-file='));
        $options = File::get($optionsFile);
        File::put(dumpTarget($process), '-- MySQL dump');

        return Process::result();
    });

    $this->artisan('db:backup')
        ->doesntExpectOutputToContain('S3cret')
        ->assertSuccessful();

    expect(File::get($this->backupDirectory.'/backup_mysql-2026-11-16_023000.sql'))->toBe('-- MySQL dump')
        ->and($options)->toContain('[client]', 'user="linkcash"', 'password="S3cret\"pass\\\\word"', 'host="db.internal"', 'port="3306"')
        ->and(file_exists($optionsFile))->toBeFalse();

    Process::assertRan(function (PendingProcess $process): bool {
        $command = implode(' ', $process->command);

        return str_contains($command, '--result-file=') && str_ends_with($command, ' linkcash') && ! str_contains($command, 'S3cret')
            && in_array('--column-statistics=0', $process->command, true);
    });
});

it('leaves out column statistics when mysqldump does not know the option', function () {
    useMysql();

    Process::fake(function (PendingProcess $process) {
        if (in_array('--help', $process->command, true)) {
            return Process::result('mysqldump  Ver 10.19 Distrib 10.11.6-MariaDB');
        }

        File::put(dumpTarget($process), '-- MariaDB dump');

        return Process::result();
    });

    $this->artisan('db:backup')->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => str_contains(implode(' ', $process->command), '--result-file=')
        && ! in_array('--column-statistics=0', $process->command, true));
});

it('deletes the partial dump when mysqldump is interrupted', function () {
    useMysql();

    Process::fake(function (PendingProcess $process) {
        if (in_array('--help', $process->command, true)) {
            return Process::result(MYSQLDUMP_HELP);
        }

        File::put(dumpTarget($process), '-- partial');

        throw new RuntimeException('The process exceeded the timeout of 3600 seconds.');
    });

    $this->artisan('db:backup')
        ->expectsOutputToContain('mysqldump failed: The process exceeded the timeout')
        ->assertFailed();

    expect(File::files($this->backupDirectory))->toBeEmpty();
});

it('explains when mysqldump is not installed', function () {
    useMysql();
    Process::fake(fn () => Process::result(errorOutput: 'mysqldump: command not found', exitCode: 127));

    $this->artisan('db:backup')->expectsOutputToContain('mysqldump was not found')->assertFailed();
});

it('reports a failed dump without the password and removes the partial file', function () {
    useMysql();

    Process::fake(function (PendingProcess $process) {
        if (in_array('--help', $process->command, true)) {
            return Process::result(MYSQLDUMP_HELP);
        }

        File::put(dumpTarget($process), '-- partial');

        return Process::result(errorOutput: "mysqldump: Got error: 1045: Access denied for user 'linkcash'@'db.internal' (using password: YES)", exitCode: 2);
    });

    $this->artisan('db:backup')
        ->expectsOutputToContain('mysqldump failed: mysqldump: Got error: 1045: Access denied')
        ->doesntExpectOutputToContain('S3cret')
        ->assertFailed();

    expect(File::files($this->backupDirectory))->toBeEmpty();
});

it('refuses drivers it cannot back up', function () {
    config([
        'database.default' => 'backup_pgsql',
        'database.connections.backup_pgsql' => ['driver' => 'pgsql', 'database' => 'linkcash', 'prefix' => ''],
    ]);

    $this->artisan('db:backup')->expectsOutputToContain('"pgsql" driver is not supported')->assertFailed();
});
