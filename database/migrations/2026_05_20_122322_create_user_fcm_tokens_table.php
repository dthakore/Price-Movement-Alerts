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
        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('fcm_token');
            $table->string('platform', 10);
            $table->timestamps();

            $table->index('user_id', 'idx_user_id');
        });

        // TEXT columns require a prefix length for unique indexes
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE user_fcm_tokens ADD UNIQUE KEY uq_user_token (user_id, fcm_token(255))'
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_fcm_tokens');
    }
};
