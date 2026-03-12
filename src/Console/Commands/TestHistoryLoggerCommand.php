<?php
// By: Fouad Fawzi - fouadfawzi.me@gmail.com

namespace FouadFawzi\HistoryLogger\Console\Commands;

use FouadFawzi\HistoryLogger\Models\History;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;

class TestHistoryLoggerCommand extends Command
{
    protected $signature = 'history-logger:test
        {model=App\\Models\\User : Model class to test}
        {--id= : Existing model id to update}
        {--email=history-demo@example.com : Demo email (used when --id is not provided)}
        {--iterations=5 : Number of updates to perform}
        {--global-max= : Temporary global max entries for this run}
        {--model-max= : Temporary max entries for this model during this run}
        {--show=10 : Number of latest logs to display}';

    protected $description = 'Run a quick CLI test for the history logger package';

    public function handle(): int
    {
        $modelClass = (string) $this->argument('model');
        $iterations = max(1, (int) $this->option('iterations'));
        $show = max(1, (int) $this->option('show'));

        if (! class_exists($modelClass)) {
            $this->error('Model class does not exist: ' . $modelClass);

            return self::FAILURE;
        }

        $model = app($modelClass);
        if (! $model instanceof Model) {
            $this->error('Provided class is not an Eloquent model.');

            return self::FAILURE;
        }

        $globalMaxOption = $this->option('global-max');
        if ($globalMaxOption !== null) {
            config(['history-logger.max_entries' => max(0, (int) $globalMaxOption)]);
        }

        $record = $this->resolveTestRecord($model);
        if (! $record instanceof Model) {
            return self::FAILURE;
        }

        $modelMaxOption = $this->option('model-max');
        if ($modelMaxOption !== null) {
            $this->setModelMaxEntries($record, max(0, (int) $modelMaxOption));
        }

        $updateColumn = $this->resolveUpdateColumn($record);
        if ($updateColumn === null) {
            $this->error('No suitable column found to update. Use --id with a model that has an updatable string/timestamp column.');

            return self::FAILURE;
        }

        for ($i = 0; $i < $iterations; $i++) {
            $value = $updateColumn === 'updated_at'
                ? now()->copy()->addSeconds($i)
                : 'History CLI Demo ' . now()->format('YmdHis') . '-' . $i;

            $record->forceFill([$updateColumn => $value])->save();
        }

        if (! method_exists($record, 'historyLogs')) {
            $this->error('The model does not expose historyLogs() relation. Make sure it uses LogsHistory trait.');

            return self::FAILURE;
        }

        $historyTable = (string) config('history-logger.table_name', 'history_logs');
        $latestLogs = $record->historyLogs()
            ->reorder($historyTable . '.id', 'desc')
            ->limit($show)
            ->get([$historyTable . '.id', $historyTable . '.event', $historyTable . '.created_at']);

        $this->newLine();
        $this->info('History logger test completed.');
        $this->line('Model class: ' . $modelClass);
        $this->line('Model ID: ' . $record->getKey());
        $this->line('Model history count: ' . $record->historyLogs()->count());
        $this->line('Global history count: ' . History::query()->count());
        $this->line('Configured global max: ' . (config('history-logger.max_entries') ?? 'null'));

        if ($latestLogs->isEmpty()) {
            $this->warn('No logs found for this model.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['id', 'event', 'created_at'],
            $latestLogs->map(static fn ($log): array => [
                'id' => (string) $log->id,
                'event' => (string) $log->event,
                'created_at' => (string) $log->created_at,
            ])->all()
        );

        return self::SUCCESS;
    }

    protected function resolveTestRecord(Model $model): ?Model
    {
        $id = $this->option('id');
        if ($id !== null && (string) $id !== '') {
            $record = $model->newQuery()->find($id);
            if (! $record instanceof Model) {
                $this->error('No model row found for --id=' . $id);

                return null;
            }

            return $record;
        }

        $email = trim((string) $this->option('email'));
        if ($email !== '' && $this->hasColumn($model, 'email')) {
            $record = $model->newQuery()->where('email', $email)->first();
            if ($record instanceof Model) {
                return $record;
            }
        }

        $record = $model->newInstance();
        $attributes = [];

        if ($email !== '' && $this->hasColumn($model, 'email')) {
            $attributes['email'] = $email;
        }

        if ($this->hasColumn($model, 'name')) {
            $attributes['name'] = 'History CLI Demo User';
        }

        if ($this->hasColumn($model, 'password')) {
            $attributes['password'] = Hash::make('password');
        }

        if ($attributes === [] && $model->newQuery()->exists()) {
            return $model->newQuery()->first();
        }

        try {
            $record->forceFill($attributes)->save();
        } catch (\Throwable $e) {
            $this->error('Could not create a demo record. Pass --id for an existing row. Error: ' . $e->getMessage());

            return null;
        }

        return $record;
    }

    protected function resolveUpdateColumn(Model $model): ?string
    {
        $preferred = ['name', 'title', 'status', 'updated_at'];

        foreach ($preferred as $column) {
            if ($this->hasColumn($model, $column)) {
                return $column;
            }
        }

        return null;
    }

    protected function hasColumn(Model $model, string $column): bool
    {
        try {
            $connection = $model->getConnectionName();

            return Schema::connection($connection)->hasColumn($model->getTable(), $column);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function setModelMaxEntries(Model $model, int $maxEntries): void
    {
        foreach (['loggerMaxEntries', 'loggerMAxEntries'] as $propertyName) {
            if (! property_exists($model, $propertyName)) {
                continue;
            }

            $property = new ReflectionProperty($model, $propertyName);
            $property->setAccessible(true);
            $property->setValue($model, $maxEntries);
        }
    }
}
