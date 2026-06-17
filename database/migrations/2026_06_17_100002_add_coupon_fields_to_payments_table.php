<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // `amount` stays the final payable; gross = pre-discount.
            $table->decimal('gross_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('gross_amount');
            $table->foreignId('coupon_id')->nullable()->after('gateway')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['gross_amount', 'discount_amount']);
        });
    }
};
