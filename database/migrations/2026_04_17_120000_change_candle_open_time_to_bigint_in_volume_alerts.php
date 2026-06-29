<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Change from timestamp to bigint to store raw Unix ms timestamp from Binance
        DB::statement('ALTER TABLE volume_alerts MODIFY candle_open_time BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE volume_alerts MODIFY candle_open_time TIMESTAMP NULL');
    }
};
