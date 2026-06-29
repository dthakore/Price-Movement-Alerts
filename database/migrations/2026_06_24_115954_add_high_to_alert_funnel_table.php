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
        Schema::table('alert_funnel', function (Blueprint $table) {
            $table->decimal('high', 20, 8)
                ->nullable()
                ->after('symbol');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alert_funnel', function (Blueprint $table) {
            $table->dropColumn('high');
        });
    }
};

