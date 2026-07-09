<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('method');
            $table->foreignId('recorded_by_admin_id')->nullable()->after('is_manual')->constrained('users')->nullOnDelete();
            $table->timestamp('manually_recorded_at')->nullable()->after('recorded_by_admin_id');
            $table->string('manual_reference')->nullable()->after('manually_recorded_at');
            $table->text('manual_note')->nullable()->after('manual_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['recorded_by_admin_id']);
            $table->dropColumn([
                'is_manual',
                'recorded_by_admin_id',
                'manually_recorded_at',
                'manual_reference',
                'manual_note',
            ]);
        });
    }
};
