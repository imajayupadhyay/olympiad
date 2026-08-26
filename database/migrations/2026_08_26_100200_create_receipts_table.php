<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained('payments')->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('series', 30)->default('default');
            $table->string('financial_year', 10);
            $table->unsignedBigInteger('sequence_number');
            $table->timestamp('issued_at');
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('company_snapshot');
            $table->json('customer_snapshot');
            $table->json('payment_snapshot');
            $table->json('line_items');
            $table->json('totals');
            $table->timestamps();

            $table->unique(['series', 'financial_year', 'sequence_number']);
            $table->index(['financial_year', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
