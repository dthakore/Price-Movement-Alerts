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
            $table->decimal('high', 18, 8)->nullable()->after('price_to');
            $table->decimal('low', 18, 8)->nullable()->after('high');
            $table->decimal('volume', 18, 8)->nullable()->after('low');
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
            $table->dropColumn(['high', 'low', 'volume']);
        });
    }
};
