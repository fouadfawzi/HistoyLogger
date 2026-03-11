<?php
// By: Fouad Fawzi - fouadfawzi.me@gmail.com

namespace FouadFawzi\HistoryLogger;

use FouadFawzi\HistoryLogger\Console\Commands\ClearHistoryCommand;
use FouadFawzi\HistoryLogger\Console\Commands\PruneHistoryCommand;
use FouadFawzi\HistoryLogger\Services\HistoryLogger;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class HistoryLoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/history-logger.php', 'history-logger');

        $this->app->singleton(HistoryLogger::class, static fn (): HistoryLogger => new HistoryLogger());
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'history-logger');
        $this->loadMigrationsFrom($this->migrationSourceDirectory());

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            PruneHistoryCommand::class,
            ClearHistoryCommand::class,
        ]);

        $this->publishes([
            __DIR__ . '/../config/history-logger.php' => config_path('history-logger.php'),
        ], 'history-logger-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/history-logger'),
        ], 'history-logger-views');

        $this->publishes([
            ...$this->migrationPublishPaths(),
        ], 'history-logger-migrations');
    }

    protected function isMultiTenantEnabled(): bool
    {
        return (bool) config('history-logger.multi_tenant', false);
    }

    protected function migrationSourceDirectory(): string
    {
        if ($this->isMultiTenantEnabled()) {
            return __DIR__ . '/../database/migrations/tenant';
        }

        return __DIR__ . '/../database/migrations';
    }

    protected function migrationPublishDirectory(): string
    {
        $targetDirectory = $this->isMultiTenantEnabled()
            ? (string) config('history-logger.tenant_migration_path', database_path('migrations/tenant'))
            : database_path('migrations');

        if (! File::isDirectory($targetDirectory)) {
            File::makeDirectory($targetDirectory, 0755, true);
        }

        return rtrim($targetDirectory, '/');
    }

    protected function migrationPublishPaths(): array
    {
        $sourceDirectory = $this->migrationSourceDirectory();
        $publishDirectory = $this->migrationPublishDirectory();
        $paths = [];

        foreach (File::files($sourceDirectory) as $file) {
            $sourcePath = $file->getRealPath();

            if (! is_string($sourcePath) || $sourcePath === '') {
                continue;
            }

            $paths[$sourcePath] = $publishDirectory . '/' . $file->getFilename();
        }

        return $paths;
    }
}
