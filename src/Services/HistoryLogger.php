<?php
// By: Fouad Fawzi - fouadfawzi.me@gmail.com

namespace FouadFawzi\HistoryLogger\Services;

use FouadFawzi\HistoryLogger\Models\History;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class HistoryLogger
{
    public function log(Model $model, string $event = 'updated'): ?History
    {
        if (! method_exists($model, 'historyLogs')) {
            return null;
        }

        $excludedColumns = $this->excludedColumns($model);
        $snapshot = Arr::except($model->attributesToArray(), $excludedColumns);
        $changes = $event === 'updated'
            ? Arr::except($model->getChanges(), $excludedColumns)
            : null;

        if ($event === 'updated' && $changes === []) {
            return null;
        }

        $connectionName = $model->getConnectionName();
        $actor = $this->resolveActor($model);

        $this->trimGlobalHistoryEntries($connectionName);
        $this->trimModelHistoryEntries($model, $connectionName);

        $history = $this->historyQuery($connectionName)
            ->create([
                'event' => $event,
                'actor_type' => $actor['type'],
                'actor_id' => $actor['id'],
                'snapshot' => $snapshot,
                'changes' => $changes,
            ]);

        $model->historyLogs()->attach($history->getKey(), ['event' => $event]);

        return $history;
    }

    protected function excludedColumns(Model $model): array
    {
        $globalExcluded = (array) config('history-logger.excluded_columns', []);
        $modelExcluded = method_exists($model, 'getHistoryLoggerExcludedColumns')
            ? (array) $model->getHistoryLoggerExcludedColumns()
            : [];

        return array_values(array_unique(array_merge($globalExcluded, $modelExcluded)));
    }

    protected function trimGlobalHistoryEntries(?string $connectionName): void
    {
        $maxEntries = (int) config('history-logger.max_entries', 0);

        if ($maxEntries <= 0) {
            return;
        }

        $currentCount = $this->historyQuery($connectionName)->count();
        $overflow = $currentCount - $maxEntries + 1;

        if ($overflow <= 0) {
            return;
        }

        $idsToDelete = $this->historyQuery($connectionName)
            ->orderBy('id')
            ->limit($overflow)
            ->pluck('id')
            ->all();

        if ($idsToDelete === []) {
            return;
        }

        $this->historyQuery($connectionName)->whereKey($idsToDelete)->delete();
    }

    protected function trimModelHistoryEntries(Model $model, ?string $connectionName): void
    {
        $modelMaxEntries = $this->resolveModelMaxEntries($model);

        if ($modelMaxEntries === null || $modelMaxEntries <= 0) {
            return;
        }

        $historyTable = (string) config('history-logger.table_name', 'history_logs');
        $pivotTable = (string) config('history-logger.pivot_table_name', 'history_loggables');
        $morphClass = $model->getMorphClass();

        $currentCount = DB::connection($connectionName)
            ->table($pivotTable)
            ->where('loggable_type', $morphClass)
            ->count();

        $overflow = $currentCount - $modelMaxEntries + 1;

        if ($overflow <= 0) {
            return;
        }

        $idsToDelete = DB::connection($connectionName)
            ->table($pivotTable)
            ->join($historyTable, $historyTable . '.id', '=', $pivotTable . '.history_log_id')
            ->where($pivotTable . '.loggable_type', $morphClass)
            ->orderBy($historyTable . '.id')
            ->limit($overflow)
            ->pluck($pivotTable . '.history_log_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($idsToDelete === []) {
            return;
        }

        DB::connection($connectionName)
            ->table($pivotTable)
            ->where('loggable_type', $morphClass)
            ->whereIn('history_log_id', $idsToDelete)
            ->delete();

        $this->deleteOrphanHistoryEntries($idsToDelete, $connectionName);
    }

    protected function resolveModelMaxEntries(Model $model): ?int
    {
        if (! method_exists($model, 'getHistoryLoggerMaxEntries')) {
            return null;
        }

        $maxEntries = $model->getHistoryLoggerMaxEntries();

        if (is_int($maxEntries)) {
            return $maxEntries;
        }

        if (is_numeric($maxEntries)) {
            return (int) $maxEntries;
        }

        return null;
    }

    protected function deleteOrphanHistoryEntries(array $historyIds, ?string $connectionName): void
    {
        $pivotTable = (string) config('history-logger.pivot_table_name', 'history_loggables');

        $linkedIds = DB::connection($connectionName)
            ->table($pivotTable)
            ->whereIn('history_log_id', $historyIds)
            ->pluck('history_log_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $orphanIds = array_values(array_diff(array_map(static fn ($id): int => (int) $id, $historyIds), $linkedIds));

        if ($orphanIds === []) {
            return;
        }

        $this->historyQuery($connectionName)->whereKey($orphanIds)->delete();
    }

    protected function historyQuery(?string $connectionName)
    {
        return (new History())
            ->setConnection($connectionName)
            ->newQuery();
    }

    protected function resolveActor(Model $model): array
    {
        if (! (bool) config('history-logger.track_actor', true)) {
            return ['type' => null, 'id' => null];
        }

        $guard = config('history-logger.actor_guard');
        $authManager = app('auth');
        $user = is_string($guard) && $guard !== ''
            ? $authManager->guard($guard)->user()
            : $authManager->user();

        if (! $user instanceof Authenticatable) {
            return ['type' => null, 'id' => null];
        }

        $identifier = $user->getAuthIdentifier();
        if ($identifier === null || $identifier === '') {
            return ['type' => null, 'id' => null];
        }

        $type = $user instanceof Model ? $user->getMorphClass() : $user::class;

        return [
            'type' => $type,
            'id' => (string) $identifier,
        ];
    }
}
