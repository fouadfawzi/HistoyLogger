<?php
// By: Fouad Fawzi - fouadfawzi.me@gmail.com

namespace FouadFawzi\HistoryLogger\Console\Commands;

use FouadFawzi\HistoryLogger\Models\History;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PruneHistoryCommand extends Command
{
    protected $signature = 'history-logger:prune
        {model=App\\Models\\User : Model class name}
        {--max= : Override max entries (if not set, uses model property)}';

    protected $description = 'Prune existing history rows for a model class to its max limit';

    public function handle(): int
    {
        $modelClass = (string) $this->argument('model');

        if (! class_exists($modelClass)) {
            $this->error('Model class does not exist: ' . $modelClass);

            return self::FAILURE;
        }

        $model = app($modelClass);
        if (! $model instanceof Model) {
            $this->error('Provided class is not an Eloquent model.');

            return self::FAILURE;
        }

        $max = $this->option('max');
        if ($max === null && method_exists($model, 'getHistoryLoggerMaxEntries')) {
            $max = $model->getHistoryLoggerMaxEntries();
        }

        $maxEntries = (int) $max;
        if ($maxEntries <= 0) {
            $this->error('A positive max is required. Pass --max or set loggerMaxEntries on the model.');

            return self::FAILURE;
        }

        $connectionName = $model->getConnectionName();
        $historyTable = (string) config('history-logger.table_name', 'history_logs');
        $pivotTable = (string) config('history-logger.pivot_table_name', 'history_loggables');
        $morphClass = $model->getMorphClass();
        $db = DB::connection($connectionName);

        $currentCount = $db->table($pivotTable)
            ->where('loggable_type', $morphClass)
            ->count();

        $overflow = $currentCount - $maxEntries;
        if ($overflow <= 0) {
            $this->info('No pruning needed. Current count: ' . $currentCount);

            return self::SUCCESS;
        }

        $idsToDelete = $db->table($pivotTable)
            ->join($historyTable, $historyTable . '.id', '=', $pivotTable . '.history_log_id')
            ->where($pivotTable . '.loggable_type', $morphClass)
            ->orderBy($historyTable . '.id')
            ->limit($overflow)
            ->pluck($pivotTable . '.history_log_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($idsToDelete === []) {
            $this->warn('No candidate rows found for pruning.');

            return self::SUCCESS;
        }

        $db->table($pivotTable)
            ->where('loggable_type', $morphClass)
            ->whereIn('history_log_id', $idsToDelete)
            ->delete();

        $linkedIds = $db->table($pivotTable)
            ->whereIn('history_log_id', $idsToDelete)
            ->pluck('history_log_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $orphanIds = array_values(array_diff($idsToDelete, $linkedIds));
        if ($orphanIds !== []) {
            (new History())
                ->setConnection($connectionName)
                ->newQuery()
                ->whereKey($orphanIds)
                ->delete();
        }

        $newCount = $db->table($pivotTable)
            ->where('loggable_type', $morphClass)
            ->count();

        $this->info('Pruning complete.');
        $this->line('Model class: ' . $modelClass);
        $this->line('Before: ' . $currentCount);
        $this->line('After: ' . $newCount);

        return self::SUCCESS;
    }
}
