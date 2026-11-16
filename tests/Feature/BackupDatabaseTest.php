<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

const BACKUP_TEST_PASSWORD = 'S3cret"pass\\word';

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 02:30:00');

    $this->workDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'db-backup-test-'.bin2hex(random_bytes(4));
    $this->backupDirectory = $this->workDirectory.DIRECTORY_SEPARATOR.'backups';
    File::ensureDirectoryExists($this->workDirectory);

    config(['database.backups.path' => $this->backupDirectory]);
});

afterEach(function () {
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

it('copies the SQLite database file with a timestamp', function () {
    File::put($this->workDirectory.'/database.sqlite', 'sqlite data');
    useSqliteFile($this->workDirectory.'/database.sqlite');

    $this->artisan('db:backup')->expectsOutputToContain('Backup written to')->assertSuccessful();

    expect(File::get($this->backupDirectory.'/backup_sqlite-2026-11-16_023000.sqlite'))->toBe('sqlite data');
});

it('keeps only the newest backups and leaves other files alone', function () {
    File::put($this->workDirectory.'/database.sqlite', 'sqlite data');
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
        if (in_array('--version', $process->command, true)) {
            return Process::result('mysqldump  Ver 8.4.0');
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

        return str_contains($command, '--result-file=') && str_ends_with($command, ' linkcash') && ! str_contains($command, 'S3cret');
    });
});

it('explains when mysqldump is not installed', function () {
    useMysql();
    Process::fake(fn () => Process::result(errorOutput: 'mysqldump: command not found', exitCode: 127));

    $this->artisan('db:backup')->expectsOutputToContain('mysqldump was not found')->assertFailed();
});

it('reports a failed dump without the password and removes the partial file', function () {
    useMysql();

    Process::fake(function (PendingProcess $process) {
        if (in_array('--version', $process->command, true)) {
            return Process::result('mysqldump  Ver 8.4.0');
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
