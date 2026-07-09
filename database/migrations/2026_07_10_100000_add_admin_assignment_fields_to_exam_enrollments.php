<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_enrollments', function (Blueprint $table) {
            $table->string('enrollment_source')->default('checkout')->after('status');
            $table->foreignId('assigned_by_admin_id')->nullable()->after('enrollment_source')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_enrollments', function (Blueprint $table) {
            $table->dropForeign(['assigned_by_admin_id']);
            $table->dropColumn(['enrollment_source', 'assigned_by_admin_id', 'assigned_at']);
        });
    }
};
