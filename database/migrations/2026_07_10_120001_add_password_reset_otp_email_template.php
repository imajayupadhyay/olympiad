<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('email_templates')->where('key', 'password_reset_otp')->exists()) {
            return;
        }

        $now = now();
        DB::table('email_templates')->insert([
            'key' => 'password_reset_otp',
            'name' => 'Password Reset Code (OTP)',
            'category' => 'notification',
            'description' => 'Sent to a student who requested a password reset. Contains a time-limited one-time code.',
            // NOTE: the OTP is intentionally kept out of the subject line — the subject
            // is persisted in email_logs, the body is not.
            'subject' => 'Your {{app_name}} password reset code',
            'html_body' => '<p>Dear {{student_name}},</p>'
                .'<p>We received a request to reset the password for your {{app_name}} student account. Use the one-time code below to continue:</p>'
                .'<p style="text-align:center;margin:22px 0;"><span style="display:inline-block;font-family:\'Space Grotesk\',Consolas,monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:#0A1024;background:#F3E9D6;border:1px solid #E7D9BE;border-radius:14px;padding:14px 26px;">{{otp}}</span></p>'
                .'<p>This code expires in <strong>{{otp_expiry_minutes}} minutes</strong> and can only be used once.</p>'
                .'<p>If you did not request this, you can safely ignore this email — your password will remain unchanged. Never share this code with anyone; our team will never ask you for it.</p>'
                .'<p>Warm regards,<br>Team {{app_name}}</p>',
            'text_body' => "Dear {{student_name}},\n\n"
                ."We received a request to reset the password for your {{app_name}} student account.\n\n"
                ."Your one-time code is: {{otp}}\n\n"
                ."This code expires in {{otp_expiry_minutes}} minutes and can only be used once.\n\n"
                ."If you did not request this, you can safely ignore this email. Never share this code with anyone; our team will never ask you for it.\n\n"
                ."Warm regards,\nTeam {{app_name}}",
            'available_variables' => json_encode(['app_name', 'portal_url', 'student_name', 'student_email', 'otp', 'otp_expiry_minutes', 'support_email', 'support_phone', 'website_url']),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'password_reset_otp')->delete();
    }
};
