<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-channel notifications (workflow.md §13.2).
 *
 * - notification_logs becomes the broadcast record for email, in-app and WhatsApp
 *   (channel/audience/status widen from enums to strings; legacy rows keep their
 *   values, `sent` → `completed`).
 * - notification_deliveries holds one row per broadcast × student × channel and is
 *   the single source of delivery status. Legacy broadcasts are backfilled from
 *   student_notifications and email_logs so history stays complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('channel', 20)->default('in_app')->change();
            $table->string('audience', 20)->default('all')->change();
            $table->string('status', 20)->default('queued')->change();
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('title');
            $table->longText('body_html')->nullable()->after('message');
            $table->string('link', 500)->nullable()->after('body_html');
            $table->json('whatsapp_template')->nullable()->after('link');
            $table->unsignedInteger('pending_count')->default(0)->after('recipient_count');
            $table->unsignedInteger('sent_count')->default(0)->after('pending_count');
            $table->unsignedInteger('delivered_count')->default(0)->after('sent_count');
            $table->unsignedInteger('read_count')->default(0)->after('delivered_count');
            $table->unsignedInteger('failed_count')->default(0)->after('read_count');
            $table->unsignedInteger('skipped_count')->default(0)->after('failed_count');
            $table->string('idempotency_key', 64)->nullable()->unique('nl_idempotency_unique');
            $table->string('fingerprint', 64)->nullable()->index('nl_fingerprint_idx');
            $table->json('meta')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_log_id')->constrained('notification_logs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('recipient_name')->nullable();
            $table->string('destination')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('provider_message_id')->nullable()->index('nd_provider_message_idx');
            $table->foreignId('email_log_id')->nullable()->constrained('email_logs')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->boolean('permanent_failure')->default(false);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('claim_token', 36)->nullable()->index('nd_claim_token_idx');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('status_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_log_id', 'user_id', 'channel'], 'nd_log_user_channel_unique');
            $table->index(['notification_log_id', 'status'], 'nd_log_status_idx');
            $table->index(['channel', 'status', 'sent_at'], 'nd_channel_status_sent_idx');
        });

        DB::table('notification_logs')->where('status', 'sent')->update(['status' => 'completed']);
        $this->backfillLegacyDeliveries();
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropUnique('nl_idempotency_unique');
            $table->dropIndex('nl_fingerprint_idx');
            $table->dropColumn([
                'subject', 'body_html', 'link', 'whatsapp_template',
                'pending_count', 'sent_count', 'delivered_count', 'read_count', 'failed_count', 'skipped_count',
                'idempotency_key', 'fingerprint', 'meta', 'completed_at',
            ]);
        });

        // WhatsApp broadcasts can't be represented by the old enum.
        DB::table('notification_logs')->where('channel', 'whatsapp')->delete();
        DB::table('notification_logs')->whereNotIn('status', ['sent', 'partial', 'failed'])->update(['status' => 'sent']);

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->enum('channel', ['email', 'in_app', 'both'])->default('in_app')->change();
            $table->enum('audience', ['all', 'exam', 'class'])->default('all')->change();
            $table->enum('status', ['sent', 'partial', 'failed'])->default('sent')->change();
        });
    }

    private function backfillLegacyDeliveries(): void
    {
        $emailStatus = ['queued' => 'pending', 'sent' => 'sent', 'failed' => 'failed', 'skipped' => 'skipped'];

        DB::table('notification_logs')->orderBy('id')->chunkById(100, function ($logs) use ($emailStatus) {
            foreach ($logs as $log) {
                $rows = [];

                if (in_array($log->channel, ['in_app', 'both'], true)) {
                    DB::table('student_notifications')
                        ->join('users', 'users.id', '=', 'student_notifications.user_id')
                        ->where('notification_log_id', $log->id)
                        ->get(['student_notifications.user_id', 'student_notifications.created_at', 'student_notifications.read_at', 'student_notifications.is_read', 'users.name'])
                        ->each(function ($n) use (&$rows, $log) {
                            $rows[] = [
                                'notification_log_id' => $log->id, 'user_id' => $n->user_id, 'channel' => 'in_app',
                                'recipient_name' => $n->name, 'destination' => null,
                                'status' => $n->is_read ? 'read' : 'sent',
                                'sent_at' => $n->created_at, 'read_at' => $n->read_at,
                                'created_at' => $n->created_at, 'updated_at' => $n->created_at,
                            ];
                        });
                }

                if (in_array($log->channel, ['email', 'both'], true)) {
                    DB::table('email_logs')
                        ->where('related_type', 'App\\Models\\NotificationLog')
                        ->where('related_id', $log->id)
                        ->whereNotNull('recipient_user_id')
                        ->get()
                        ->unique('recipient_user_id')
                        ->each(function ($e) use (&$rows, $log, $emailStatus) {
                            $status = $emailStatus[$e->status] ?? 'failed';
                            $rows[] = [
                                'notification_log_id' => $log->id, 'user_id' => $e->recipient_user_id, 'channel' => 'email',
                                'recipient_name' => $e->recipient_name, 'destination' => $e->recipient_email,
                                'status' => $status, 'email_log_id' => $e->id,
                                'error' => $e->error_message,
                                'sent_at' => $e->sent_at, 'read_at' => null,
                                'created_at' => $e->created_at, 'updated_at' => $e->updated_at,
                            ];
                        });
                }

                foreach (array_chunk($this->normalise($rows), 500) as $chunk) {
                    DB::table('notification_deliveries')->insertOrIgnore($chunk);
                }

                $counts = DB::table('notification_deliveries')->where('notification_log_id', $log->id)
                    ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

                DB::table('notification_logs')->where('id', $log->id)->update([
                    'subject' => $log->channel === 'in_app' ? null : $log->title,
                    'pending_count' => (int) ($counts['pending'] ?? 0),
                    'sent_count' => (int) ($counts['sent'] ?? 0) + (int) ($counts['read'] ?? 0),
                    'read_count' => (int) ($counts['read'] ?? 0),
                    'failed_count' => (int) ($counts['failed'] ?? 0),
                    'skipped_count' => (int) ($counts['skipped'] ?? 0),
                    'completed_at' => $log->sent_at,
                ]);
            }
        });
    }

    /** insertOrIgnore needs every row to have the same columns. */
    private function normalise(array $rows): array
    {
        $columns = ['notification_log_id', 'user_id', 'channel', 'recipient_name', 'destination', 'status', 'email_log_id', 'error', 'sent_at', 'read_at', 'created_at', 'updated_at'];

        return array_map(fn (array $row) => array_merge(array_fill_keys($columns, null), $row), $rows);
    }
};
