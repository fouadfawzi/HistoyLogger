# Laravel History Logger

`history-logger` is a Laravel package that tracks model changes and stores JSON snapshots for each event in `history_logs`.

It helps developers by giving a built-in model history layer without writing repetitive audit logic in every project.

Key benefits:
- Automatic logging on model events (`created`, `updated`, `deleted`)
- Full data snapshot per entry for easier debugging and traceability
- Actor tracking (`actor_type`, `actor_id`) to know who changed what
- Built-in maintenance commands for pruning and clearing history data
- Optional ready-to-use history viewer page for fast inspection

## Requirements

- PHP 8.0+
- Laravel 8.75, 9.x, 10.x, 11.x, 12.x

## Installation

```bash
composer require fouadfawzi/history-logger
```

## Publish Package Files

Publish config:

```bash
php artisan vendor:publish --provider="FouadFawzi\HistoryLogger\HistoryLoggerServiceProvider" --tag="history-logger-config"
```

Publish migrations:

```bash
php artisan vendor:publish --provider="FouadFawzi\HistoryLogger\HistoryLoggerServiceProvider" --tag="history-logger-migrations"
```

Migration publishing behavior:
- Default (`multi_tenant=false`, `only_tenant_mode=false`): publishes **main** migrations only.
- `multi_tenant=true`: publishes **main + tenant** migrations.
- `only_tenant_mode=true`: publishes **tenant** migrations only (main is skipped).

Optional explicit tags:

```bash
php artisan vendor:publish --provider="FouadFawzi\HistoryLogger\HistoryLoggerServiceProvider" --tag="history-logger-migrations-main"
php artisan vendor:publish --provider="FouadFawzi\HistoryLogger\HistoryLoggerServiceProvider" --tag="history-logger-migrations-tenant"
```

Optional: publish views:

```bash
php artisan vendor:publish --provider="FouadFawzi\HistoryLogger\HistoryLoggerServiceProvider" --tag="history-logger-views"
```

Run migrations:

```bash
php artisan migrate
```

## Basic Usage

Add the trait to any model:

```php
<?php

namespace App\Models;

use FouadFawzi\HistoryLogger\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use LogsHistory;

    // Per-model cap: keep only latest 1000 entries for this model.
    protected int $loggerMaxEntries = 1000;
}
```

Now `created`, `updated`, and `deleted` events are logged automatically.

## Who Made The Change (Actor)

The package can store who performed each change:
- `actor_type` (for example `App\Models\User`)
- `actor_id` (for example `5`)

By default, actor tracking is enabled and uses the current authenticated user.
If an action runs from CLI/queue/system without an authenticated user, actor fields stay `null`.

## Excluding Model Columns

You can exclude specific columns per model using a protected array:

```php
protected array $historyLoggerExcluded = [
    'updated_at',
    'internal_note',
];
```

You can also set global exclusions in `config/history-logger.php`:

```php
'excluded_columns' => ['created_at'],
```

## Accessing History

Each model using the trait gets a `historyLogs()` relation:

```php
$order = Order::find(1);
$logs = $order->historyLogs()->latest()->get();
```

## Configuration Options

Main config file: `config/history-logger.php`

Useful options:

```php
'events' => ['created', 'updated', 'deleted'],
'track_actor' => true,
'actor_guard' => null,
'max_entries' => 10000000,
'excluded_columns' => [],
'ignored_models' => [],
'multi_tenant' => false,
'only_tenant_mode' => false,
'tenant_migration_path' => database_path('migrations/tenant'),
'table_name' => 'history_logs',
'pivot_table_name' => 'history_loggables',
```

### Global Max Entries

Use `max_entries` to set a hard cap for the total number of rows in `history_logs`.

```php
'max_entries' => 10000000,
```

When this limit is reached, the package removes the oldest entries first, then inserts the newest one.  
This keeps the table size from ever growing above the configured limit.

### Per-Model Max Entries

For model-level caps, define this property on the model:

```php
protected int $loggerMaxEntries = 1000;
```

When this model class reaches 1000 history entries (for example all `User` logs), each new log entry removes the oldest one for that model class.

If you already use `loggerMAxEntries` naming in your codebase, it is also supported.

## Multi-Tenant Mode

If your app is multi-tenant:

1. Publish config.
2. Set:

```php
'multi_tenant' => true,
'only_tenant_mode' => false, // keeps main + tenant publishing
'tenant_migration_path' => database_path('migrations/tenant'),
```

3. Publish migrations again with the same tag:

```bash
php artisan vendor:publish --provider="FouadFawzi\HistoryLogger\HistoryLoggerServiceProvider" --tag="history-logger-migrations"
```

4. Run tenant migrations using your tenant workflow.

If you want tenant-only mode:

```php
'multi_tenant' => true,
'only_tenant_mode' => true, // skips main migration publishing
```

## Optional History Viewer Route

This package ships with a simple Blade page to inspect history entries from the browser.

1. Enable the route and map your model alias in `config/history-logger.php`:

```php
'route' => [
    'enabled' => true,
    'prefix' => 'history-logger',
    'middleware' => ['web', 'auth'], // recommended
    'model_map' => [
        'orders' => App\Models\Order::class,
        'users' => App\Models\User::class,
    ],
],
```

2. Make sure the mapped model uses the `LogsHistory` trait.

3. Open:

`/history-logger/orders/1`

Where:
- `orders` is the key from `model_map`
- `1` is the model ID

4. Optional: publish and customize the Blade view:

```bash
php artisan vendor:publish --provider="FouadFawzi\HistoryLogger\HistoryLoggerServiceProvider" --tag="history-logger-views"
```

Published view location:

`resources/views/vendor/history-logger/history.blade.php`

## Maintenance Commands

Prune logs for a model class:

```bash
php artisan history-logger:prune "App\\Models\\User" --max=1000
```

This keeps only the latest 1000 history entries for `App\Models\User` and deletes older rows.
If related `history_logs` rows become orphaned, they are removed too.
`--max` is optional. If omitted, the command uses the model property `loggerMaxEntries` (if defined).

Clear all history rows and reset auto-increment:

```bash
php artisan history-logger:clear --force
```

`--force` skips the confirmation prompt and executes the delete immediately.

## License

MIT
