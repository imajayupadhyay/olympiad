<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('school_code', 50)->nullable()->after('id');
            $table->string('state', 100)->nullable()->after('address')->index();
            $table->string('district', 100)->nullable()->after('state')->index();
            $table->string('city', 100)->nullable()->after('district')->index();
            $table->string('pin_code', 10)->nullable()->after('city')->index();
            $table->string('email')->nullable()->after('pin_code')->index();
            $table->string('mobile', 25)->nullable()->after('email');
            $table->string('head_phone', 25)->nullable()->after('mobile');
            $table->boolean('is_managed')->default(false)->after('is_active')->index();

            $table->unique('school_code');
            $table->index(['is_managed', 'state', 'district', 'city'], 'schools_management_location_index');
        });

        Schema::create('school_coordinators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email')->nullable()->index();
            $table->string('phone', 25)->nullable()->index();
            $table->string('designation', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_coordinators');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['school_code']);
            $table->dropIndex(['state']);
            $table->dropIndex(['district']);
            $table->dropIndex(['city']);
            $table->dropIndex(['pin_code']);
            $table->dropIndex(['email']);
            $table->dropIndex(['is_managed']);
            $table->dropIndex('schools_management_location_index');

            $table->dropColumn([
                'school_code',
                'state',
                'district',
                'city',
                'pin_code',
                'email',
                'mobile',
                'head_phone',
                'is_managed',
            ]);
        });
    }
};
