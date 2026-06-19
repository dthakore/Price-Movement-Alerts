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
    public function up()
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            $table->boolean('candle')->nullable()->after('funnel_id')->comment('1 for green, 0 for red');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            $table->dropColumn(['candle']);
        });
    }
};
