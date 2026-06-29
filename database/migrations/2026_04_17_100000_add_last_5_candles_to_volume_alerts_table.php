<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('volume_alerts', function (Blueprint $table) {
            $table->json('last_5_candles')->nullable()->after('before_24_hours_price');
        });
    }

    public function down(): void
    {
        Schema::table('volume_alerts', function (Blueprint $table) {
            $table->dropColumn('last_5_candles');
        });
    }
};
