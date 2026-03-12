<?php
// By: Fouad Fawzi - fouadfawzi.me@gmail.com

namespace FouadFawzi\HistoryLogger\Support;

use DateTimeInterface;
use FouadFawzi\HistoryLogger\Models\History;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class HistoryLog
{
    public static function list(
        DateTimeInterface|string|null $startDate = null,
        DateTimeInterface|string|null $endDate = null
    ): Collection {
        $query = History::query();

        self::applyDateRange($query, $startDate, $endDate);

        return $query->orderByDesc('id')->get();
    }

    public static function getBy(
        ?string $model = null,
        ?array $actor = null,
        DateTimeInterface|string|null $startDate = null,
        DateTimeInterface|string|null $endDate = null
    ): Collection {
        [$actorType, $actorId] = self::parseActorFilter($actor);

        $history = new History();
        $historyTable = $history->getTable();
        $pivotTable = (string) config('history-logger.pivot_table_name', 'history_loggables');

        $query = $history->newQuery()->from($historyTable);

        if ($model !== null && $model !== '') {
            $modelMorphType = self::resolveModelMorphType($model);

            $query->whereIn($historyTable . '.id', static function ($subQuery) use ($pivotTable, $modelMorphType): void {
                $subQuery->from($pivotTable)
                    ->select($pivotTable . '.history_log_id')
                    ->where($pivotTable . '.loggable_type', $modelMorphType);
            });
        }

        if ($actorType !== null && $actorType !== '') {
            $query->where($historyTable . '.actor_type', $actorType);
        }

        if ($actorId !== null) {
            $query->where($historyTable . '.actor_id', (string) $actorId);
        }

        self::applyDateRange($query, $startDate, $endDate, $historyTable);

        return $query->orderByDesc($historyTable . '.id')->get();
    }

    protected static function parseActorFilter(?array $actor): array
    {
        if ($actor === null) {
            return [null, null];
        }

        $actorType = $actor['type'] ?? $actor[0] ?? null;
        $actorId = $actor['id'] ?? $actor[1] ?? null;

        if ($actorType !== null && ! is_string($actorType)) {
            throw new InvalidArgumentException('actor type must be a string class name.');
        }

        if ($actorType !== null) {
            $actorType = trim($actorType);
            if ($actorType === '') {
                $actorType = null;
            }
        }

        if ($actorId !== null && ! is_scalar($actorId)) {
            throw new InvalidArgumentException('actor id must be a scalar value.');
        }

        if ($actorId !== null && $actorType === null) {
            throw new InvalidArgumentException('actor type is required when actor id is provided.');
        }

        return [$actorType, $actorId !== null ? (string) $actorId : null];
    }

    protected static function applyDateRange(
        Builder $query,
        DateTimeInterface|string|null $startDate,
        DateTimeInterface|string|null $endDate,
        string $table = ''
    ): void {
        $createdAtColumn = $table !== '' ? $table . '.created_at' : 'created_at';

        if ($startDate !== null) {
            $query->where($createdAtColumn, '>=', self::parseDate($startDate));
        }

        if ($endDate !== null) {
            $query->where($createdAtColumn, '<=', self::parseDate($endDate));
        }
    }

    protected static function parseDate(DateTimeInterface|string $value): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value);
    }

    protected static function resolveModelMorphType(string $modelClass): string
    {
        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException('Model class does not exist: ' . $modelClass);
        }

        $model = app($modelClass);
        if (! $model instanceof Model) {
            throw new InvalidArgumentException('Provided model argument is not an Eloquent model class.');
        }

        return $model->getMorphClass();
    }
}
