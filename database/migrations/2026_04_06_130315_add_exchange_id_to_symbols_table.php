<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('symbols', function (Blueprint $table) {

            $table->foreignId('exchange_id')->nullable()->constrained('exchanges')->onDelete('cascade');

        });
    }

    public function down(): void
    {
        Schema::table('symbols', function (Blueprint $table) {

            // Drop foreign key first
            $table->dropForeign('exchange_id');
            $table->dropColumn('exchange_id');
        });
    }
};
