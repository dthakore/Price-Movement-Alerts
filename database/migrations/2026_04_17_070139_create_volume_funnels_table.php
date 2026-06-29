<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('volume_funnels', function (Blueprint $table) {
            $table->id();

            // Symbol
            $table->string('symbol')->index();

            // Relation to volume_alert
            $table->foreignId('volume_alert_id')
                ->constrained('volume_alerts')
                ->cascadeOnDelete();

            // Optional: funnel stage (if you scale later)
            $table->unsignedTinyInteger('funnel_step')->default(1);

            $table->timestamps();

            // Prevent duplicate funnel entries for same alert
            $table->unique(['volume_alert_id', 'funnel_step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volume_funnels');
    }
};
