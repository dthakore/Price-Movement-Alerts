<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('volume_alerts', function (Blueprint $table) {
            $table->id();

            // Symbol (e.g. BTCUSDT)
            $table->string('symbol')->index();

            // Volume data
            $table->float('avg_volume')->nullable();
            $table->float('current_volume')->nullable();
            $table->float('buy_volume')->nullable();
            $table->float('buy_volume_percentage')->nullable();
            $table->float('trades')->nullable();

            // Momentum / spike indicator (can be % or custom metric)
            $table->float('volume_moment')->nullable();

            // Price data
            $table->float('open_price')->nullable();
            $table->float('high_price')->nullable();
            $table->float('low_price')->nullable();
            $table->float('close_price')->nullable();
            $table->float('before_24_hours_price')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volume_alerts');
    }
};
