<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category')->default('transactional');
            $table->string('description')->nullable();
            $table->string('subject');
            $table->longText('html_body');
            $table->longText('text_body')->nullable();
            $table->json('available_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('email_templates')->insert([
            [
                'key' => 'student_registered',
                'name' => 'Student Registration Welcome',
                'category' => 'student',
                'description' => 'Sent after a student account is created from public registration or admin.',
                'subject' => 'Welcome to {{app_name}}',
                'html_body' => '<p>Dear Parent/Student,</p><p>Greetings from the {{app_name}} Team!</p><p>Thank you for registering with {{app_name}}. Your student account has been created successfully.</p><p>Student Login Details</p><p>Portal: {{portal_url}}<br>Username: {{student_email}}<br>Password: {{login_password}}</p><p>You can log in using the above credentials to view your registration details and access future updates related to the Olympiad. For your security, we recommend changing your password after your first login.</p><p>If you have any questions or require assistance, please feel free to contact us.</p><p>Email: {{support_email}}<br>Phone: {{support_phone}}<br>Website: {{website_url}}</p><p>Warm regards,<br>Team {{app_name}}</p>',
                'text_body' => "Dear Parent/Student,\n\nGreetings from the {{app_name}} Team!\n\nThank you for registering with {{app_name}}. Your student account has been created successfully.\n\nStudent Login Details\n\nPortal: {{portal_url}}\nUsername: {{student_email}}\nPassword: {{login_password}}\n\nYou can log in using the above credentials to view your registration details and access future updates related to the Olympiad. For your security, we recommend changing your password after your first login.\n\nIf you have any questions or require assistance, please feel free to contact us.\n\nEmail: {{support_email}}\nPhone: {{support_phone}}\nWebsite: {{website_url}}\n\nWarm regards,\n\nTeam {{app_name}}",
                'available_variables' => json_encode(['app_name', 'portal_url', 'student_name', 'student_email', 'login_password', 'student_class', 'school_name', 'city', 'state', 'support_email', 'support_phone', 'website_url']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'payment_success',
                'name' => 'Payment Success Confirmation',
                'category' => 'payment',
                'description' => 'Sent after Razorpay or fully-discounted coupon payment is fulfilled.',
                'subject' => 'Payment received for {{olympiad_name}}',
                'html_body' => '<p>Dear Parent/Student,</p><p>Greetings from the {{app_name}} Team!</p><p>Thank you for registering for the {{olympiad_name}}. We are delighted to confirm that your payment has been successfully received through {{payment_gateway}} and your registration has been completed successfully.</p><p>Payment Details</p><p>Student Name: {{student_name}}<br>School Name: {{school_name}}<br>Olympiad: {{olympiad_name}}<br>Amount Paid: {{amount_paid}}<br>Transaction ID: {{transaction_id}}<br>Payment Date & Time: {{payment_datetime}}<br>Payment Method: {{payment_method}}</p><p>Student Login Details</p><p>Portal: {{portal_url}}<br>Username: {{student_email}}<br>Password: {{login_password}}</p><p>You can log in using the above credentials to view your registration details and access future updates related to the Olympiad. For your security, we recommend changing your password after your first login.</p><p>Further information regarding the examination, including the exam schedule, admit card, and important instructions, will be shared through your registered email and will also be available on your student portal.</p><p>If you have any questions or require assistance, please feel free to contact us.</p><p>Email: {{support_email}}<br>Phone: {{support_phone}}<br>Website: {{website_url}}</p><p>Thank you for being a part of the {{app_name}}. We wish you all the very best for your examination.</p><p>Warm regards,<br>Team {{app_name}}</p>',
                'text_body' => "Dear Parent/Student,\n\nGreetings from the {{app_name}} Team!\n\nThank you for registering for the {{olympiad_name}}. We are delighted to confirm that your payment has been successfully received through {{payment_gateway}} and your registration has been completed successfully.\n\nPayment Details\n\nStudent Name: {{student_name}}\nSchool Name: {{school_name}}\nOlympiad: {{olympiad_name}}\nAmount Paid: {{amount_paid}}\nTransaction ID: {{transaction_id}}\nPayment Date & Time: {{payment_datetime}}\nPayment Method: {{payment_method}}\n\nStudent Login Details\n\nPortal: {{portal_url}}\nUsername: {{student_email}}\nPassword: {{login_password}}\n\nYou can log in using the above credentials to view your registration details and access future updates related to the Olympiad. For your security, we recommend changing your password after your first login.\n\nFurther information regarding the examination, including the exam schedule, admit card, and important instructions, will be shared through your registered email and will also be available on your student portal.\n\nIf you have any questions or require assistance, please feel free to contact us.\n\nEmail: {{support_email}}\nPhone: {{support_phone}}\nWebsite: {{website_url}}\n\nThank you for being a part of the {{app_name}}. We wish you all the very best for your examination.\n\nWarm regards,\n\nTeam {{app_name}}",
                'available_variables' => json_encode(['app_name', 'portal_url', 'student_name', 'student_email', 'login_password', 'school_name', 'olympiad_name', 'exam_names', 'amount_paid', 'transaction_id', 'payment_datetime', 'payment_method', 'payment_gateway', 'support_email', 'support_phone', 'website_url']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'exam_reminder',
                'name' => 'Upcoming Exam Reminder',
                'category' => 'exam',
                'description' => 'Sent by the scheduled reminder command before an enrolled exam starts.',
                'subject' => 'Reminder: {{exam_name}} starts on {{exam_start_datetime}}',
                'html_body' => '<p>Dear {{student_name}},</p><p>This is a reminder that your exam {{exam_name}} is scheduled to start on {{exam_start_datetime}}.</p><p>Duration: {{exam_duration}} minutes<br>Class: {{student_class}}</p><p>Please log in to your student portal before the exam time and read all instructions carefully.</p><p>Portal: {{portal_url}}</p><p>Warm regards,<br>Team {{app_name}}</p>',
                'text_body' => "Dear {{student_name}},\n\nThis is a reminder that your exam {{exam_name}} is scheduled to start on {{exam_start_datetime}}.\n\nDuration: {{exam_duration}} minutes\nClass: {{student_class}}\n\nPlease log in to your student portal before the exam time and read all instructions carefully.\n\nPortal: {{portal_url}}\n\nWarm regards,\nTeam {{app_name}}",
                'available_variables' => json_encode(['app_name', 'portal_url', 'student_name', 'student_email', 'student_class', 'exam_name', 'exam_code', 'exam_start_datetime', 'exam_end_datetime', 'exam_duration', 'support_email', 'support_phone', 'website_url']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'result_released',
                'name' => 'Result Released',
                'category' => 'result',
                'description' => 'Sent when admin releases processed results.',
                'subject' => 'Your {{exam_name}} result is now available',
                'html_body' => '<p>Dear {{student_name}},</p><p>Your result for {{exam_name}} has been released.</p><p>Score: {{score}} / {{max_score}}<br>Percentage: {{percentage}}%<br>National Rank: {{national_rank}}<br>Grade: {{grade}}</p><p>You can view the detailed scorecard in your student portal.</p><p>Portal: {{portal_url}}</p><p>Warm regards,<br>Team {{app_name}}</p>',
                'text_body' => "Dear {{student_name}},\n\nYour result for {{exam_name}} has been released.\n\nScore: {{score}} / {{max_score}}\nPercentage: {{percentage}}%\nNational Rank: {{national_rank}}\nGrade: {{grade}}\n\nYou can view the detailed scorecard in your student portal.\n\nPortal: {{portal_url}}\n\nWarm regards,\nTeam {{app_name}}",
                'available_variables' => json_encode(['app_name', 'portal_url', 'student_name', 'student_email', 'exam_name', 'score', 'max_score', 'percentage', 'national_rank', 'grade', 'support_email', 'support_phone', 'website_url']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'certificate_issued',
                'name' => 'Certificate Issued',
                'category' => 'certificate',
                'description' => 'Sent when certificates are made available to students.',
                'subject' => 'Your {{exam_name}} certificate is available',
                'html_body' => '<p>Dear {{student_name}},</p><p>Your certificate for {{exam_name}} is now available in your student portal.</p><p>Please log in and download it from the Certificates section.</p><p>Portal: {{portal_url}}</p><p>Warm regards,<br>Team {{app_name}}</p>',
                'text_body' => "Dear {{student_name}},\n\nYour certificate for {{exam_name}} is now available in your student portal.\n\nPlease log in and download it from the Certificates section.\n\nPortal: {{portal_url}}\n\nWarm regards,\nTeam {{app_name}}",
                'available_variables' => json_encode(['app_name', 'portal_url', 'student_name', 'student_email', 'exam_name', 'support_email', 'support_phone', 'website_url']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'notification_blast',
                'name' => 'Admin Email Announcement',
                'category' => 'notification',
                'description' => 'Wrapper used for email channel messages sent from the Notifications page.',
                'subject' => '{{notification_title}}',
                'html_body' => '<p>Dear {{student_name}},</p><p>{{notification_message}}</p><p>Portal: {{portal_url}}</p><p>Warm regards,<br>Team {{app_name}}</p>',
                'text_body' => "Dear {{student_name}},\n\n{{notification_message}}\n\nPortal: {{portal_url}}\n\nWarm regards,\nTeam {{app_name}}",
                'available_variables' => json_encode(['app_name', 'portal_url', 'student_name', 'student_email', 'notification_title', 'notification_message', 'support_email', 'support_phone', 'website_url']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
