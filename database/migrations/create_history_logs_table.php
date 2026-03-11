<?php
// By: Fouad Fawzi - fouadfawzi.me@gmail.com

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('history-logger.table_name', 'history_logs'), function (Blueprint $table): void {
            $table->id();
            $table->string('event', 32);
            $table->string('actor_type')->nullable()->index();
            $table->string('actor_id')->nullable()->index();
            $table->json('snapshot');
            $table->json('changes')->nullable();
            $table->timestamps();
        });

        Schema::create(config('history-logger.pivot_table_name', 'history_loggables'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('history_log_id')
                ->constrained(config('history-logger.table_name', 'history_logs'))
                ->cascadeOnDelete();
            $table->morphs('loggable');
            $table->string('event', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('history-logger.pivot_table_name', 'history_loggables'));
        Schema::dropIfExists(config('history-logger.table_name', 'history_logs'));
    }
};
