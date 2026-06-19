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
            //
            $table->string('source_job')->default('price_alert');

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
            //
            $table->dropColumn('source_job');
        });
    }
};
