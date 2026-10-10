<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            // Snapshot of the per-section breakdown taken when results are processed, so a
            // released scorecard does not drift if the paper's sections are edited later.
            $table->json('section_scores')->nullable()->after('percentage');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('section_scores');
        });
    }
};
