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
        Schema::create('cron_job_logs', function (Blueprint $table) {
            $table->id();
            $table->string('cron_job');                                    // command signature
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->unsignedInteger('bots_processed')->nullable();         // active bots / reference_ids dispatched
            $table->unsignedInteger('trades_dispatched')->nullable();      // trade status jobs dispatched
            $table->unsignedInteger('alerts_processed')->nullable();       // alert configs dispatched
            $table->unsignedInteger('keys_processed')->nullable();         // API keys iterated
            $table->text('error')->nullable();                             // exception message on failure
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
        Schema::dropIfExists('cron_job_logs');
    }
};
