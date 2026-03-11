<?php

namespace FouadFawzi\HistoryLogger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class HistoryLoggable extends Model
{
    protected $fillable = [
        'history_log_id',
        'loggable_type',
        'loggable_id',
        'event',
    ];

    public function getTable(): string
    {
        return (string) config('history-logger.pivot_table_name', 'history_loggables');
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(History::class, 'history_log_id');
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
