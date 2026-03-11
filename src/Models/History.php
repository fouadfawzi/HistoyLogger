<?php

namespace FouadFawzi\HistoryLogger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class History extends Model
{
    protected $fillable = [
        'event',
        'actor_type',
        'actor_id',
        'snapshot',
        'changes',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'changes' => 'array',
    ];

    public function getTable(): string
    {
        return (string) config('history-logger.table_name', 'history_logs');
    }

    public function loggableEntries(): HasMany
    {
        return $this->hasMany(HistoryLoggable::class, 'history_log_id');
    }

    public function actor(): MorphTo
    {
        return $this->morphTo('actor');
    }
}
