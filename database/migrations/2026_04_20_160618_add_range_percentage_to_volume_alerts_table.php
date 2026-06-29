<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volume_alerts', function (Blueprint $table) {
            $table->double('range_percentage')->nullable()->after('z_score_last_192_candles');
        });
    }

    public function down(): void
    {
        Schema::table('volume_alerts', function (Blueprint $table) {
            $table->dropColumn('range_percentage');
        });
    }
};
