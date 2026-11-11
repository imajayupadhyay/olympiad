<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hall ticket layout configuration (single row, JSON) and admin-defined student
 * fields with their per-student values (§12.15). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hall_ticket_settings', function (Blueprint $table) {
            $table->id();
            // Each column is a normalised JSON document; HallTicketLayoutService owns the shape.
            $table->json('fields')->nullable();        // candidate details: [{key, enabled, label, wide}]
            $table->json('exam_columns')->nullable();  // schedule columns: [{key, enabled, label}]
            $table->json('text_rows')->nullable();     // admin-typed rows: [{id, label, value}]
            $table->json('instructions')->nullable();  // ["…", "…"]
            $table->json('layout')->nullable();        // titles, photo, columns, signatures, theme…
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('student_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();       // immutable slug, used in ticket config as custom:{key}
            $table->string('label', 80);
            $table->string('type', 20)->default('text'); // text | date | select
            $table->json('options')->nullable();       // select choices
            $table->string('placeholder', 120)->nullable();
            $table->string('help_text', 200)->nullable();
            $table->boolean('is_required')->default(false); // counts toward completion + blocks submit
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('student_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_custom_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('value', 255)->nullable();
            $table->timestamps();
            $table->unique(['student_custom_field_id', 'user_id'], 'scf_values_field_user_unique');
        });

        // Starter fields the owner asked about; optional and hidden on the ticket until enabled.
        $now = now();
        DB::table('student_custom_fields')->insert([
            ['key' => 'father_name', 'label' => "Father's name", 'type' => 'text', 'options' => null, 'placeholder' => 'As in school records', 'help_text' => null, 'is_required' => false, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'mother_name', 'label' => "Mother's name", 'type' => 'text', 'options' => null, 'placeholder' => 'As in school records', 'help_text' => null, 'is_required' => false, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => json_encode(['Male', 'Female', 'Other']), 'placeholder' => null, 'help_text' => null, 'is_required' => false, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'school_admission_no', 'label' => 'School ID / admission no.', 'type' => 'text', 'options' => null, 'placeholder' => 'Printed on your school ID card', 'help_text' => null, 'is_required' => false, 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('student_custom_field_values');
        Schema::dropIfExists('student_custom_fields');
        Schema::dropIfExists('hall_ticket_settings');
    }
};
