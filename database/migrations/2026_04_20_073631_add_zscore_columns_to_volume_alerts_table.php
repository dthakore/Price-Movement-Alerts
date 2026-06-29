<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('volume_alerts', function (Blueprint $table) {
            $table->double('z_score_last_10_candles')->nullable()->after('volume_moment');
            $table->double('z_score_last_192_candles')->nullable()->after('z_score_last_10_candles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('volume_alerts', function (Blueprint $table) {
            $table->dropColumn([
                'z_score_last_10_candles',
                'z_score_last_192_candles'
            ]);
        });
    }
};
