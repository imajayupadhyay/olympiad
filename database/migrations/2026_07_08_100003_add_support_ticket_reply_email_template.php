<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('email_templates')->where('key', 'support_ticket_reply')->exists()) {
            return;
        }

        $now = now();
        DB::table('email_templates')->insert([
            'key' => 'support_ticket_reply',
            'name' => 'Support Ticket Reply',
            'category' => 'notification',
            'description' => 'Sent to a student when the support team replies to their ticket.',
            'subject' => 'Re: {{ticket_subject}} — {{app_name}} Support',
            'html_body' => '<p>Dear {{student_name}},</p><p>Our support team has replied to your ticket <strong>{{ticket_subject}}</strong>.</p><p>{{reply_snippet}}</p><p>Log in to your student portal to read the full reply and continue the conversation.</p><p>Portal: {{ticket_url}}</p><p>Warm regards,<br>Team {{app_name}}</p>',
            'text_body' => "Dear {{student_name}},\n\nOur support team has replied to your ticket \"{{ticket_subject}}\".\n\n{{reply_snippet}}\n\nLog in to your student portal to read the full reply and continue the conversation.\n\nPortal: {{ticket_url}}\n\nWarm regards,\nTeam {{app_name}}",
            'available_variables' => json_encode(['app_name', 'portal_url', 'student_name', 'student_email', 'ticket_subject', 'reply_snippet', 'ticket_url', 'support_email', 'support_phone', 'website_url']),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'support_ticket_reply')->delete();
    }
};
