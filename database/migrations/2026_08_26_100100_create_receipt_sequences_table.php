<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('series', 30)->default('default');
            $table->string('financial_year', 10);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['series', 'financial_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_sequences');
    }
};
