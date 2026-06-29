<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('volume_alerts', function (Blueprint $table) {
            $table->unsignedBigInteger('candle_open_time')->nullable()->after('symbol');
        });
    }

    public function down(): void
    {
        Schema::table('volume_alerts', function (Blueprint $table) {
            $table->dropColumn('candle_open_time');
        });
    }
};
