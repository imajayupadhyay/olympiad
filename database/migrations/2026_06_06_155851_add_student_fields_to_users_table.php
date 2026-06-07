<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('class_level_id')->nullable()->constrained('class_levels')->nullOnDelete()->after('role');
            $table->string('phone', 15)->nullable()->after('class_level_id');
            $table->date('dob')->nullable()->after('phone');
            $table->string('school')->nullable()->after('dob');
            $table->string('city')->nullable()->after('school');
            $table->string('state')->nullable()->after('city');
            $table->string('photo')->nullable()->after('state');
            $table->boolean('is_active')->default(true)->after('photo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['class_level_id']);
            $table->dropColumn(['class_level_id', 'phone', 'dob', 'school', 'city', 'state', 'photo', 'is_active']);
        });
    }
};
