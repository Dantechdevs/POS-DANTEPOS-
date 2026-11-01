<?php

namespace App\Console\Commands;

use App\Services\AfricasTalkingSms;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--keep= : Days of daily backups to keep (default from config)}
        {--list : List existing backups and exit}
        {--no-alert : Do not SMS the owner if the backup fails}';

    protected $description = 'Dump the database to a verified, compressed file and prune old backups';

    public function handle()
    {
        $dir = config('backup.path') ?: storage_path('app/backups');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return $this->failed("Cannot create backup folder: {$dir}");
        }

        if ($this->option('list')) {
            return $this->listBackups($dir);
        }

        $sql = null;
        try {
            $conn = config('database.connections.'.config('database.default'));
            if (! in_array($conn['driver'] ?? '', ['mysql', 'mariadb'], true)) {
                throw new \RuntimeException('Only MySQL/MariaDB databases are supported.');
            }

            $dump = config('backup.mysqldump');
            if (! $dump || ! file_exists($dump)) {
                throw new \RuntimeException("mysqldump not found at '{$dump}'. Set BACKUP_MYSQLDUMP in .env.");
            }

            $base = config('backup.prefix').'-'.now()->format('Y-m-d_His');
            $sql = $dir.DIRECTORY_SEPARATOR.$base.'.sql';
            $gz = $sql.'.gz';

            $pw = (string) ($conn['password'] ?? '');
            $process = new Process([
                $dump,
                '--host='.($conn['host'] ?? '127.0.0.1'),
                '--port='.($conn['port'] ?? 3306),
                '--user='.$conn['username'],
                '--single-transaction', '--quick', '--routines', '--triggers',
                '--default-character-set=utf8mb4',
                '--result-file='.$sql,
                $conn['database'],
            ], null, $pw !== '' ? ['MYSQL_PWD' => $pw] : []);
            $process->setTimeout(900);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump failed.');
            }
            if (! is_file($sql) || filesize($sql) < 1024) {
                throw new \RuntimeException('Backup file is missing or suspiciously small.');
            }
            if (! $this->looksComplete($sql)) {
                throw new \RuntimeException('Backup file is incomplete (no completion marker at the end).');
            }

            $in = fopen($sql, 'rb');
            $out = gzopen($gz, 'wb6');
            while (! feof($in)) {
                gzwrite($out, fread($in, 1048576));
            }
            fclose($in);
            gzclose($out);
            unlink($sql);
            $sql = null;

            if (! is_file($gz) || filesize($gz) < 200) {
                throw new \RuntimeException('Compressed backup is missing or too small.');
            }

            $this->info('Backup saved: '.$gz.' ('.$this->size(filesize($gz)).')');

            $copy = config('backup.copy_path');
            if ($copy) {
                if (! is_dir($copy) && ! @mkdir($copy, 0775, true) && ! is_dir($copy)) {
                    $this->warn("Could not reach the copy folder '{$copy}'. Backup is saved locally only.");
                    Log::warning("Backup copy folder unreachable: {$copy}");
                } elseif (! @copy($gz, rtrim($copy, '\\/').DIRECTORY_SEPARATOR.basename($gz))) {
                    $this->warn("Could not copy the backup to '{$copy}'.");
                    Log::warning("Backup copy to {$copy} failed");
                } else {
                    $this->info('Copied to '.$copy);
                }
            }

            $this->prune($dir, (int) ($this->option('keep') ?: config('backup.keep_days')));
            if ($copy && is_dir($copy)) {
                $this->prune($copy, (int) ($this->option('keep') ?: config('backup.keep_days')), true);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($sql && is_file($sql)) {
                @unlink($sql);
            }

            return $this->failed($e->getMessage());
        }
    }

    private function looksComplete(string $file): bool
    {
        $size = filesize($file);
        $h = fopen($file, 'rb');
        fseek($h, max(0, $size - 1024));
        $tail = (string) fread($h, 1024);
        fclose($h);

        return strpos($tail, 'Dump completed') !== false;
    }

    private function prune(string $dir, int $keepDays, bool $quiet = false): void
    {
        $cut = Carbon::today()->subDays(max(1, $keepDays));
        $removed = 0;

        foreach (glob($dir.DIRECTORY_SEPARATOR.config('backup.prefix').'-*.sql.gz') ?: [] as $f) {
            if (! preg_match('/-(\d{4}-\d{2}-\d{2})_\d{6}\.sql\.gz$/', $f, $m)) {
                continue;
            }
            $d = Carbon::parse($m[1]);
            if ($d->lt($cut) && $d->day !== 1) { // keep the 1st of every month
                @unlink($f);
                $removed++;
            }
        }

        if ($removed && ! $quiet) {
            $this->line("Pruned {$removed} old backup(s).");
        }
    }

    private function listBackups(string $dir)
    {
        $files = glob($dir.DIRECTORY_SEPARATOR.config('backup.prefix').'-*.sql.gz') ?: [];
        rsort($files);

        if (! $files) {
            $this->warn('No backups yet in '.$dir);

            return self::SUCCESS;
        }

        $this->table(['File', 'Size', 'Created'], array_map(fn ($f) => [
            basename($f), $this->size(filesize($f)), date('Y-m-d H:i', filemtime($f)),
        ], $files));

        return self::SUCCESS;
    }

    private function size(int $b): string
    {
        return $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : number_format($b / 1024, 0).' KB';
    }

    private function failed(string $reason)
    {
        $this->error('BACKUP FAILED: '.$reason);
        Log::error('Database backup failed: '.$reason);

        if (! $this->option('no-alert')) {
            try {
                $sms = app(AfricasTalkingSms::class);
                $phones = array_filter(array_map('trim', explode(',', (string) config('summary.phones'))));
                if ($sms->configured() && $phones) {
                    $msg = 'DantePOS BACKUP FAILED '.now()->format('d/m H:i').': '.mb_substr($reason, 0, 100);
                    foreach ($phones as $p) {
                        $sms->send($p, $msg);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Backup failure alert not sent: '.$e->getMessage());
            }
        }

        return self::FAILURE;
    }
}
