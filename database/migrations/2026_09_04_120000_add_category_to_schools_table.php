<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('school_code')->index();
            $table->index(['is_managed', 'category', 'school_code'], 'schools_data_entry_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropIndex('schools_data_entry_category_index');
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
