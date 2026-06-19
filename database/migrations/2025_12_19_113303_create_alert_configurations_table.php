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
        Schema::create('alert_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->text('symbols'); // BTCUSDT
            $table->float('percentage'); // X%
            $table->boolean('direction')->default(1);
            $table->string('time_duration'); // 1h
            $table->integer('time_duration_minutes'); // (eg: 60)
            $table->integer('frequency_minutes'); // (eg: 10)
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_run_at')->nullable();
            $table->timestamps();
//            $table->index(['symbols', 'is_active']);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('alert_configurations');
    }
};
