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
        Schema::table('alert_configurations', function (Blueprint $table) {
            $table->enum('type', ['normal', 'high reversion'])
                ->default('normal')
                ->after('id');

            $table->decimal('reversion_percentage', 8, 2)
                ->nullable()
                ->after('type');
                
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alert_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'reversion_percentage',
            ]);

        });
    }
};
