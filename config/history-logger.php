<?php

use FouadFawzi\HistoryLogger\Models\History;
use FouadFawzi\HistoryLogger\Models\HistoryLoggable;

return [
    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */
    'table_name' => 'history_logs',
    'pivot_table_name' => 'history_loggables',

    /*
    |--------------------------------------------------------------------------
    | Events To Track
    |--------------------------------------------------------------------------
    */
    'events' => ['created', 'updated', 'deleted'],

    /*
    |--------------------------------------------------------------------------
    | Actor Tracking
    |--------------------------------------------------------------------------
    | If enabled, each history row stores who performed the action using:
    | actor_type + actor_id (from the authenticated user).
    | For CLI/queue/system actions without auth user, both are null.
    */
    'track_actor' => true,
    'actor_guard' => null,

    /*
    |--------------------------------------------------------------------------
    | Global Max Entries
    |--------------------------------------------------------------------------
    | Set a hard limit for total rows in history_logs table.
    | If limit is reached, oldest rows are removed before adding new entries.
    | Set to null or 0 to disable.
    */
    'max_entries' => env('HISTORY_LOGGER_MAX_ENTRIES', 10000000),

    /*
    |--------------------------------------------------------------------------
    | Excluded Columns
    |--------------------------------------------------------------------------
    | Global columns to skip from snapshot/changes.
    | Per-model exclusions can be passed from the model itself.
    */
    'excluded_columns' => [],

    /*
    |--------------------------------------------------------------------------
    | Ignore Models
    |--------------------------------------------------------------------------
    */
    'ignored_models' => [
        History::class,
        HistoryLoggable::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi Tenant Mode
    |--------------------------------------------------------------------------
    | Publishing behavior:
    | - Default: publish main migrations only.
    | - multi_tenant=true: publish main + tenant migrations.
    | - only_tenant_mode=true: publish tenant migrations only.
    |
    | Loading behavior for `php artisan migrate`:
    | - Default: load main migrations.
    | - only_tenant_mode=true: load tenant migrations.
    */
    'multi_tenant' => env('HISTORY_LOGGER_MULTI_TENANT', false),
    'only_tenant_mode' => env('HISTORY_LOGGER_ONLY_TENANT_MODE', false),
    'tenant_migration_path' => database_path('migrations/tenant'),

    /*
    |--------------------------------------------------------------------------
    | Optional History Route UI
    |--------------------------------------------------------------------------
    */
    'route' => [
        'enabled' => env('HISTORY_LOGGER_ROUTE_ENABLED', false),
        'prefix' => env('HISTORY_LOGGER_ROUTE_PREFIX', 'history-logger'),
        'middleware' => ['web'],
        'per_page' => 25,

        /*
        | Example:
        | 'model_map' => [
        |     'users' => App\Models\User::class,
        |     'orders' => App\Models\Order::class,
        | ],
        */
        'model_map' => [],
    ],
];
