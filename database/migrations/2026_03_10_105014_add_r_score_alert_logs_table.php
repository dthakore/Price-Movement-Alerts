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
            $table->float('r3')->nullable();
            $table->float('r5')->nullable();
            $table->float('r15')->nullable();
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
            $table->dropColumn('r3');
            $table->dropColumn('r5');
            $table->dropColumn('r15');
        });
    }
};
