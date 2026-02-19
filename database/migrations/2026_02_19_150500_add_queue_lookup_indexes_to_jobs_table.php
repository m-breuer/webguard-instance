<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->index(['queue', 'reserved_at', 'available_at'], 'jobs_queue_reserved_available_idx');
            $table->index(['queue', 'available_at', 'id'], 'jobs_queue_available_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('jobs_queue_reserved_available_idx');
            $table->dropIndex('jobs_queue_available_id_idx');
        });
    }
};
