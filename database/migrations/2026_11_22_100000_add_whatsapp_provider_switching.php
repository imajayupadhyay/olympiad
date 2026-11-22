<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp provider switching (workflow.md §13.6). Additive only:
 *
 * - messaging_settings: single row, the active WhatsApp provider (default bird).
 * - messaging_provider_configs: one row per provider; non-secret settings (JSON) and
 *   credentials (encrypted with APP_KEY). Empty values fall back to the server .env.
 * - whatsapp_templates: locally managed templates for providers whose catalogue is not
 *   read live (MSG91). Bird templates stay live from Bird and are never stored here.
 * - notification_logs.provider / notification_deliveries.provider: which provider a
 *   broadcast and each message went through. Existing WhatsApp rows are backfilled to bird.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_settings', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_provider', 20)->default('bird');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('switched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messaging_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->unique('mpc_provider_unique');
            $table->json('settings')->nullable();
            $table->text('credentials')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('last_check_ok')->nullable();
            $table->string('last_check_message', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('name', 100);
            $table->string('display_name', 150)->nullable();
            $table->string('language', 12)->default('en');
            $table->string('category', 20)->default('utility');
            $table->string('external_id', 100)->nullable();
            $table->string('header', 60)->nullable();
            $table->text('body');
            $table->string('footer', 60)->nullable();
            $table->json('buttons')->nullable();
            $table->json('variable_examples')->nullable();
            $table->string('approval_status', 20)->default('approved');
            $table->boolean('is_active')->default(true);
            $table->string('source', 20)->default('manual');
            $table->timestamp('synced_at')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider', 'name', 'language'], 'wat_provider_name_lang_unique');
            $table->index(['provider', 'is_active'], 'wat_provider_active_idx');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('provider', 20)->nullable()->after('channel')->index('nl_provider_idx');
        });

        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->string('provider', 20)->nullable()->after('channel');
            $table->index(['provider', 'channel', 'status'], 'nd_provider_channel_status_idx');
        });

        DB::table('notification_logs')->where('channel', 'whatsapp')->whereNull('provider')->update(['provider' => 'bird']);
        DB::table('notification_deliveries')->where('channel', 'whatsapp')->whereNull('provider')->update(['provider' => 'bird']);
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropIndex('nd_provider_channel_status_idx');
            $table->dropColumn('provider');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex('nl_provider_idx');
            $table->dropColumn('provider');
        });

        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('messaging_provider_configs');
        Schema::dropIfExists('messaging_settings');
    }
};
