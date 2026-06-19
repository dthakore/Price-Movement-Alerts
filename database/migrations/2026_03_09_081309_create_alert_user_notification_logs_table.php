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
        Schema::create('alert_user_notification_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('alert_log_id')
                ->constrained('alert_logs')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->boolean('email_sent')->default(0);

            $table->text('response')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['alert_log_id','user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('alert_user_notification_logs');
    }
};
