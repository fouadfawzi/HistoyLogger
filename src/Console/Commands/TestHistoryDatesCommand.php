<?php
// By: Fouad Fawzi - fouadfawzi.me@gmail.com

namespace FouadFawzi\HistoryLogger\Console\Commands;

use FouadFawzi\HistoryLogger\Support\HistoryLog;
use Illuminate\Console\Command;
use InvalidArgumentException;

class TestHistoryDatesCommand extends Command
{
    protected $signature = 'history-logger:test-dates
        {--model= : Optional model class filter}
        {--actor-type= : Optional actor type (required when actor-id is used)}
        {--actor-id= : Optional actor id}
        {--start= : Optional start date/time}
        {--end= : Optional end date/time}
        {--preview=5 : Number of latest matching rows to preview}';

    protected $description = 'Test history retrieval by date range with optional model/actor filters';

    public function handle(): int
    {
        $model = $this->normalizeOption($this->option('model'));
        $actorType = $this->normalizeOption($this->option('actor-type'));
        $actorId = $this->normalizeOption($this->option('actor-id'));
        $startDate = $this->normalizeOption($this->option('start'));
        $endDate = $this->normalizeOption($this->option('end'));
        $preview = max(0, (int) $this->option('preview'));

        $actor = null;
        if ($actorType !== null || $actorId !== null) {
            $actor = ['type' => $actorType, 'id' => $actorId];
        }

        try {
            $global = HistoryLog::list($startDate, $endDate);
            $filtered = HistoryLog::getBy($model, $actor, $startDate, $endDate);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Date test failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('History date test finished.');
        $this->line('start: ' . ($startDate ?? 'null'));
        $this->line('end: ' . ($endDate ?? 'null'));
        $this->line('model: ' . ($model ?? 'null'));
        $this->line('actor type: ' . ($actorType ?? 'null'));
        $this->line('actor id: ' . ($actorId ?? 'null'));
        $this->line('global count (list): ' . $global->count());
        $this->line('filtered count (getBy): ' . $filtered->count());

        if ($preview > 0 && $filtered->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['id', 'event', 'actor_type', 'actor_id', 'created_at'],
                $filtered
                    ->take($preview)
                    ->map(static fn ($row): array => [
                        'id' => (string) $row->id,
                        'event' => (string) $row->event,
                        'actor_type' => (string) ($row->actor_type ?? ''),
                        'actor_id' => (string) ($row->actor_id ?? ''),
                        'created_at' => (string) ($row->created_at ?? ''),
                    ])
                    ->values()
                    ->all()
            );
        }

        return self::SUCCESS;
    }

    protected function normalizeOption(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            $value = (string) $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
