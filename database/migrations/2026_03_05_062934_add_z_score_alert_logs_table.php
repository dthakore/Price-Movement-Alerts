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
        //
        Schema::table('alert_logs', function (Blueprint $table) {
            //
            $table->float('z_score_1d')->nullable();
            $table->float('z_score_2d')->nullable();
            $table->float('z_score_3d')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('alert_logs', function (Blueprint $table) {
            //
            $table->dropColumn('z_score_1d');
            $table->dropColumn('z_score_2d');
            $table->dropColumn('z_score_3d');
        });
    }
};
