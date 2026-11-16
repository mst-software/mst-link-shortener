<?php

namespace App\Console\Commands;

use App\Services\ScheduleHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Number;
use RuntimeException;
use SplFileInfo;
use Throwable;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup {--keep=14 : How many backups of the database to keep; older ones are deleted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Back up the default database (mysqldump for MySQL and MariaDB, a file copy for SQLite)';

    /**
     * Longest a dump may run before it is stopped.
     */
    private const DUMP_TIMEOUT_SECONDS = 3600;

    public function handle(): int
    {
        $keep = filter_var($this->option('keep'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($keep === false) {
            $this->error('The --keep option must be a whole number of at least 1.');

            return self::FAILURE;
        }

        $connection = (string) config('database.default');
        $directory = (string) config('database.backups.path');
        $basePath = $directory.DIRECTORY_SEPARATOR.$connection.'-'.now()->format('Y-m-d_His');

        try {
            $config = DB::connection($connection)->getConfig();
            File::ensureDirectoryExists($directory, 0700);

            $file = match ($config['driver'] ?? null) {
                'sqlite' => $this->backUpSqlite($config, $basePath.'.sqlite'),
                'mysql', 'mariadb' => $this->backUpMysql($config, $basePath.'.sql'),
                default => throw new RuntimeException('Backing up the "'.($config['driver'] ?? '?').'" driver is not supported. Use the database server\'s own backup tool.'),
            };
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup written to '.$file.' ('.Number::fileSize((int) filesize($file)).').');

        $deleted = $this->deleteOldBackups($directory, $connection, $keep);

        if ($deleted > 0) {
            $this->line('Deleted '.$deleted.' older backup(s), keeping the latest '.$keep.'.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function backUpSqlite(array $config, string $file): string
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === '' || $database === ':memory:' || ! is_file($database)) {
            throw new RuntimeException('The SQLite database file was not found, so there is nothing to back up.');
        }

        if (! File::copy($database, $file)) {
            throw new RuntimeException('The SQLite database file could not be copied to '.$file.'.');
        }

        return $file;
    }

    /**
     * Dump with mysqldump. The credentials go in a temporary option file readable by this user only,
     * never on the command line, where other users of the server could see them.
     *
     * @param  array<string, mixed>  $config
     */
    private function backUpMysql(array $config, string $file): string
    {
        if (! $this->mysqldumpIsAvailable()) {
            throw new RuntimeException('mysqldump was not found. Install the MySQL or MariaDB client tools on this server (for example the "mysql-client" or "mariadb-client" package).');
        }

        $optionsFile = $this->writeOptionsFile($config);

        try {
            $result = Process::timeout(self::DUMP_TIMEOUT_SECONDS)->run([
                'mysqldump',
                '--defaults-extra-file='.$optionsFile,
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--result-file='.$file,
                (string) $config['database'],
            ]);
        } finally {
            File::delete($optionsFile);
        }

        if ($result->failed() || ! is_file($file) || filesize($file) === 0) {
            File::delete($file);

            throw new RuntimeException('mysqldump failed: '.(ScheduleHealth::summarizeFailure($result->errorOutput()) ?: 'exited with code '.$result->exitCode().'.'));
        }

        return $file;
    }

    private function mysqldumpIsAvailable(): bool
    {
        try {
            return Process::run(['mysqldump', '--version'])->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function writeOptionsFile(array $config): string
    {
        $path = tempnam(sys_get_temp_dir(), 'db-backup-');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file for the database credentials.');
        }

        chmod($path, 0600);

        $options = ['user' => $config['username'] ?? '', 'password' => $config['password'] ?? ''];

        if (filled($config['unix_socket'] ?? null)) {
            $options['socket'] = $config['unix_socket'];
        } else {
            $options['host'] = $config['host'] ?? '127.0.0.1';
            $options['port'] = $config['port'] ?? 3306;
        }

        $lines = ['[client]'];

        foreach ($options as $name => $value) {
            $lines[] = $name.'="'.addcslashes((string) $value, '\\"').'"';
        }

        File::put($path, implode(PHP_EOL, $lines).PHP_EOL);

        return $path;
    }

    /**
     * Delete all but the newest $keep backups of the connection. Names sort by their timestamp.
     */
    private function deleteOldBackups(string $directory, string $connection, int $keep): int
    {
        $pattern = '/\A'.preg_quote($connection, '/').'-\d{4}-\d{2}-\d{2}_\d{6}\.(sql|sqlite)\z/';

        $outdated = collect(File::files($directory))
            ->filter(fn (SplFileInfo $file): bool => preg_match($pattern, $file->getFilename()) === 1)
            ->sortByDesc(fn (SplFileInfo $file): string => $file->getFilename())
            ->slice($keep);

        $outdated->each(fn (SplFileInfo $file): bool => File::delete($file->getPathname()));

        return $outdated->count();
    }
}
