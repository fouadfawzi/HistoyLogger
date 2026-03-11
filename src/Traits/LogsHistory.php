<?php
// By: Fouad Fawzi - fouadfawzi.me@gmail.com

namespace FouadFawzi\HistoryLogger\Traits;

use FouadFawzi\HistoryLogger\Models\History;
use FouadFawzi\HistoryLogger\Services\HistoryLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;

trait LogsHistory
{
    public static function bootLogsHistory(): void
    {
        foreach (static::historyLoggerEvents() as $event) {
            static::{$event}(static function (Model $model) use ($event): void {
                if (! static::shouldLogHistoryEvent($model, $event)) {
                    return;
                }

                app(HistoryLogger::class)->log($model, $event);
            });
        }
    }

    public function historyLogs(): MorphToMany
    {
        return $this->morphToMany(
            History::class,
            'loggable',
            config('history-logger.pivot_table_name', 'history_loggables'),
            'loggable_id',
            'history_log_id'
        )
            ->withPivot('event')
            ->withTimestamps()
            ->orderByDesc(config('history-logger.table_name', 'history_logs') . '.id');
    }

    public function getHistoryLoggerExcludedColumns(): array
    {
        if (! property_exists($this, 'historyLoggerExcluded')) {
            return [];
        }

        $excluded = $this->historyLoggerExcluded;

        return is_array($excluded) ? $excluded : [];
    }

    public function getHistoryLoggerMaxEntries(): ?int
    {
        $maxEntries = null;

        if (property_exists($this, 'loggerMaxEntries')) {
            $maxEntries = $this->loggerMaxEntries;
        } elseif (property_exists($this, 'loggerMAxEntries')) {
            // Backward-compatible support for typoed property naming.
            $maxEntries = $this->loggerMAxEntries;
        }

        if ($maxEntries === null) {
            return null;
        }

        if (is_int($maxEntries)) {
            return $maxEntries > 0 ? $maxEntries : null;
        }

        if (is_numeric($maxEntries)) {
            $parsedValue = (int) $maxEntries;

            return $parsedValue > 0 ? $parsedValue : null;
        }

        return null;
    }

    protected static function historyLoggerEvents(): array
    {
        $allowedEvents = ['created', 'updated', 'deleted', 'restored'];
        $configuredEvents = (array) config('history-logger.events', ['created', 'updated', 'deleted']);

        return array_values(array_filter(
            $configuredEvents,
            static fn ($event): bool => is_string($event) && in_array($event, $allowedEvents, true)
        ));
    }

    protected static function shouldLogHistoryEvent(Model $model, string $event): bool
    {
        if (in_array($model::class, (array) config('history-logger.ignored_models', []), true)) {
            return false;
        }

        if ($event !== 'updated') {
            return true;
        }

        $changes = Arr::except($model->getChanges(), $model->getHistoryLoggerExcludedColumns());

        return $changes !== [];
    }
}
