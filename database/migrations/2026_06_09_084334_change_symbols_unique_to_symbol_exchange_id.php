<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('symbols', function (Blueprint $table) {
            $table->dropUnique(['symbol']);
            $table->unique(['symbol', 'exchange_id']);
        });
    }

    public function down(): void
    {
        Schema::table('symbols', function (Blueprint $table) {
            $table->dropUnique(['symbol', 'exchange_id']);
            $table->unique(['symbol']);
        });
    }
};
