<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('National Olympiad Hunt');
            $table->string('gstin', 15)->nullable();
            $table->text('address')->nullable();
            $table->string('state', 100)->nullable();
            $table->string('state_code', 2)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('hsn_sac', 20)->nullable();
            $table->string('service_description')->default('Online Olympiad Exam Registration');
            $table->decimal('gst_rate', 5, 2)->default(18);
            $table->boolean('prices_include_gst')->default(true);
            $table->string('receipt_prefix', 60)->default('NOH/{FY}/');
            $table->unsignedTinyInteger('receipt_padding')->default(4);
            $table->unsignedTinyInteger('financial_year_start_month')->default(4);
            $table->json('visible_fields')->nullable();
            $table->text('footer_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_settings');
    }
};
