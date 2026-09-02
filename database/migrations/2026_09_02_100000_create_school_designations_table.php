<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_designations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        $designations = [
            'Principal',
            'Vice Principal',
            'Academic Head',
            'Olympiad Coordinator',
            'Exam Coordinator',
            'School Coordinator',
            'Head of Department',
            'Teacher',
            'Administrative Officer',
            'Accounts Officer',
        ];

        DB::table('school_designations')->insert(array_map(
            fn (string $name, int $index) => [
                'name' => $name,
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $designations,
            array_keys($designations),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('school_designations');
    }
};
