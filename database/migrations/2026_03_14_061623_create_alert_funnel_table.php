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
        Schema::create('alert_funnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_configuration_id')->constrained('alert_configurations')->cascadeOnDelete('cascade');
            $table->string('symbol');
            $table->integer('funnel_id')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('alert_funnel');
    }
};
