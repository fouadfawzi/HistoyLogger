<?php
// By: Fouad Fawzi - fouadfawzi.me@gmail.com

namespace FouadFawzi\HistoryLogger\Console\Commands;

use FouadFawzi\HistoryLogger\Models\History;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearHistoryCommand extends Command
{
    protected $signature = 'history-logger:clear
        {--force : Run without confirmation}';

    protected $description = 'Delete all rows from history logger tables and reset auto-increment';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $confirmed = $this->confirm('This will delete all history logs and pivot rows. Continue?', false);

            if (! $confirmed) {
                $this->warn('Cancelled.');

                return self::SUCCESS;
            }
        }

        $historyModel = new History();
        $connectionName = $historyModel->getConnectionName();
        $historyTable = (string) config('history-logger.table_name', 'history_logs');
        $pivotTable = (string) config('history-logger.pivot_table_name', 'history_loggables');
        $db = DB::connection($connectionName);

        $deletedPivot = $db->table($pivotTable)->delete();
        $deletedHistory = $db->table($historyTable)->delete();
        $driver = $db->getDriverName();

        $resetIdentity = static function (string $table) use ($db, $driver): void {
            if ($table === '') {
                return;
            }

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $db->statement('ALTER TABLE ' . $db->getQueryGrammar()->wrapTable($table) . ' AUTO_INCREMENT = 1');

                return;
            }

            if ($driver === 'sqlite') {
                $db->delete('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);

                return;
            }

            if ($driver === 'pgsql') {
                $row = $db->selectOne("SELECT pg_get_serial_sequence(?, 'id') AS sequence_name", [$table]);
                $sequence = is_object($row) ? (string) ($row->sequence_name ?? '') : '';

                if ($sequence !== '') {
                    $db->unprepared('ALTER SEQUENCE ' . $sequence . ' RESTART WITH 1');
                }

                return;
            }

            if ($driver === 'sqlsrv') {
                $db->unprepared("DBCC CHECKIDENT ('{$table}', RESEED, 0)");
            }
        };

        try {
            $resetIdentity($pivotTable);
            $resetIdentity($historyTable);
        } catch (\Throwable $e) {
            $this->warn('Rows were deleted, but identity reset was skipped: ' . $e->getMessage());
        }

        $remainingPivot = $db->table($pivotTable)->count();
        $remainingHistory = $db->table($historyTable)->count();

        $this->info('History tables cleared.');
        $this->line("Deleted from {$pivotTable}: {$deletedPivot}");
        $this->line("Deleted from {$historyTable}: {$deletedHistory}");
        $this->line("Remaining in {$pivotTable}: {$remainingPivot}");
        $this->line("Remaining in {$historyTable}: {$remainingHistory}");
        $this->line('Auto-increment reset: attempted for both tables.');

        return self::SUCCESS;
    }
}
