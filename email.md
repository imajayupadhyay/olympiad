# Email Management System

## Overview

The portal now has a managed transactional email system for student-facing emails.
Admins can edit templates from the admin panel, enable or disable each email type,
preview rendered emails, send test emails, and view Brevo delivery logs.

Brevo transactional email is sent through:

`POST https://api.brevo.com/v3/smtp/email`

The API key is read from `BREVO_API_KEY` and passed as Brevo's `api-key` request
header.

## Admin Page

New admin page:

`/admin/emails`

Sidebar location:

`Finance & Comms -> Emails`

Admin can manage:

- Template name
- Description
- Subject
- HTML body through the existing TipTap WYSIWYG editor
- Plain text body
- On/off status per template
- Preview with sample data
- Test email queueing
- Recent delivery logs

## Branded Email Design

All managed email templates are wrapped in a central branded HTML shell from
`ManagedEmailService::wrapHtml()`.

The shell uses the app's v2 Editorial / Prestige theme:

- Warm paper page background
- White email card
- Deep ink header
- Saffron accent border and CTA
- Image-free NEO brand mark in the header, so email clients do not show broken images
- Support email and phone in the footer
- Mobile-safe responsive spacing

The admin still edits the actual message body per template. The brand wrapper is
centralized so every transactional email stays visually consistent.

## Database Changes

Added migrations:

- `database/migrations/2026_07_03_100000_create_email_templates_table.php`
- `database/migrations/2026_07_03_100001_create_email_logs_table.php`

Tables:

- `email_templates`
- `email_logs`

Default templates are inserted by the migration:

- `student_registered`
- `payment_success`
- `exam_reminder`
- `result_released`
- `certificate_issued`
- `notification_blast`

## Backend Files Added

- `app/Models/EmailTemplate.php`
- `app/Models/EmailLog.php`
- `app/Services/ManagedEmailService.php`
- `app/Jobs/SendManagedEmail.php`
- `app/Http/Controllers/Admin/EmailTemplateController.php`
- `app/Console/Commands/SendExamReminderEmails.php`
- `tests/Feature/ManagedEmailSystemTest.php`

## Brevo Config

Added to `config/services.php`:

- `services.brevo.api_key`
- `services.brevo.endpoint`
- `services.brevo.sender_email`
- `services.brevo.sender_name`
- `services.brevo.reply_to_email`
- `services.brevo.reply_to_name`
- `services.brevo.support_email`
- `services.brevo.support_phone`

Added to `.env.example`:

```env
BREVO_API_KEY=
BREVO_ENDPOINT=https://api.brevo.com/v3/smtp/email
BREVO_SENDER_EMAIL=info@neoexam.org
BREVO_SENDER_NAME="National Excellence Olympiad"
BREVO_REPLY_TO_EMAIL=info@neoexam.org
BREVO_REPLY_TO_NAME="National Excellence Olympiad"
SUPPORT_EMAIL=info@neoexam.org
SUPPORT_PHONE="+91 72890 89009"
```

If `BREVO_API_KEY` is missing, emails are still queued/logged, but the job marks
the email log as `failed` with the error `BREVO_API_KEY is not configured.`

## Email Triggers Wired

### Student Registration

Template:

`student_registered`

Triggered from:

- Public student registration
- Admin-created student account

Files changed:

- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Http/Controllers/Admin/UserController.php`

### Payment Success

Template:

`payment_success`

Triggered after payment fulfilment/enrolment:

- Razorpay verified payment path
- Razorpay webhook paid path
- Fully discounted coupon enrolment path

File changed:

- `app/Services/PaymentService.php`

### Exam Reminder

Template:

`exam_reminder`

Command:

```bash
php artisan emails:send-exam-reminders --hours=24
```

Scheduled in `routes/console.php`:

```php
Schedule::command('emails:send-exam-reminders --hours=24')->hourly();
```

The command sends one reminder per student per exam. Duplicate reminders are
blocked through `email_logs.related_type` and `email_logs.related_id`.

### Result Released

Template:

`result_released`

Triggered when admin releases results.

File changed:

- `app/Http/Controllers/Admin/ResultController.php`

### Certificate Issued

Template:

`certificate_issued`

Triggered when admin makes certificates available to students. It only queues
email for newly created student certificate records, so repeated clicks do not
spam students.

File changed:

- `app/Http/Controllers/Admin/CertificateController.php`

### Admin Notification Email Channel

Template:

`notification_blast`

Existing admin Notifications email channel now uses the managed Brevo email
service instead of the previous default `Mail::queue` path.

File changed:

- `app/Http/Controllers/Admin/NotificationController.php`

## Template Variables

Variables use this format:

```text
{{student_name}}
{{student_email}}
{{exam_name}}
{{amount_paid}}
```

All variable values are escaped before rendering to avoid injecting unsafe HTML.
Admin-authored template HTML is intentionally stored as HTML because the admin
WYSIWYG editor is the content source.

## Queue Requirement

Emails are dispatched through application queue jobs:

`App\Jobs\SendManagedEmail`

Production should run a queue worker, for example:

```bash
php artisan queue:work
```

The existing dev script already starts:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

## Verification Done

Commands run:

```bash
php artisan migrate
npm run build
php artisan test --filter=ManagedEmailSystemTest
php artisan route:list --name=admin.emails
php artisan list emails
```

Result:

- Email migrations applied successfully.
- Vite build passed.
- `ManagedEmailSystemTest` passed: 6 tests, 18 assertions.
- Admin email routes registered.
- Exam reminder command registered.

Full `php artisan test` was also run. The new email tests passed, but the full
suite still has pre-existing unrelated failures around missing default Breeze
routes such as `dashboard` / `profile`, plus the default homepage test not using
the migrated app schema.
