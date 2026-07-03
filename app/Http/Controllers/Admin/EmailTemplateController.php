<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\ManagedEmailService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = EmailTemplate::with('updatedBy:id,name')
            ->withCount('logs')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $logs = EmailLog::with(['template:id,name,key', 'recipient:id,name,email'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Emails/Index', [
            'templates' => $templates,
            'logs' => $logs,
            'stats' => [
                'templates' => EmailTemplate::count(),
                'active' => EmailTemplate::where('is_active', true)->count(),
                'sent' => EmailLog::where('status', 'sent')->count(),
                'failed' => EmailLog::where('status', 'failed')->count(),
            ],
            'brevo' => [
                'configured' => filled(config('services.brevo.api_key')),
                'sender_email' => config('services.brevo.sender_email'),
                'sender_name' => config('services.brevo.sender_name'),
            ],
        ]);
    }

    public function update(Request $request, EmailTemplate $template)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'subject' => 'required|string|max:190',
            'html_body' => 'required|string',
            'text_body' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $template->update(array_merge($data, [
            'updated_by' => auth()->id(),
        ]));

        return back()->with('success', 'Email template updated successfully.');
    }

    public function toggle(Request $request, EmailTemplate $template)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $template->update([
            'is_active' => $data['is_active'],
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', $template->is_active ? 'Email template enabled.' : 'Email template disabled.');
    }

    public function preview(Request $request, EmailTemplate $template, ManagedEmailService $emails)
    {
        $sampleVariables = collect($template->available_variables ?: [])
            ->mapWithKeys(fn ($key) => [$key => $this->sampleValue($key)])
            ->all();

        return response()->json($emails->renderTemplate($template, $sampleVariables));
    }

    public function sendTest(Request $request, EmailTemplate $template, ManagedEmailService $emails)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:120',
        ]);

        $sampleVariables = collect($template->available_variables ?: [])
            ->mapWithKeys(fn ($key) => [$key => $this->sampleValue($key)])
            ->all();

        $rendered = $emails->renderTemplate($template, $sampleVariables);

        $log = EmailLog::create([
            'email_template_id' => $template->id,
            'template_key' => $template->key,
            'recipient_email' => $data['email'],
            'recipient_name' => $data['name'] ?? 'Test Recipient',
            'subject' => $rendered['subject'],
            'status' => 'queued',
            'meta' => ['test' => true, 'sent_by' => auth()->id()],
            'queued_at' => now(),
        ]);

        \App\Jobs\SendManagedEmail::dispatch($log->id, $rendered);

        return back()->with('success', 'Test email queued.');
    }

    protected function sampleValue(string $key): string
    {
        return match ($key) {
            'app_name' => config('app.name', 'National Olympiad Hunt'),
            'portal_url', 'website_url' => config('app.url'),
            'student_name' => 'Aarav Sharma',
            'student_email', 'username' => 'aarav@example.com',
            'login_password' => 'Sample@123',
            'student_class' => 'Class 8',
            'school_name' => 'Delhi Public School',
            'city' => 'New Delhi',
            'state' => 'Delhi',
            'olympiad_name', 'exam_name' => 'National Excellence Olympiad',
            'exam_names' => 'National Excellence Olympiad',
            'exam_code' => 'NEO-2026',
            'exam_start_datetime' => now()->addDays(2)->format('d M Y, h:i A'),
            'exam_end_datetime' => now()->addDays(2)->addHour()->format('d M Y, h:i A'),
            'exam_duration' => '60',
            'amount_paid' => '₹499.00',
            'transaction_id' => 'pay_TEST123456',
            'payment_datetime' => now()->format('d M Y, h:i A'),
            'payment_method' => 'UPI',
            'payment_gateway' => 'Razorpay',
            'score' => '82',
            'max_score' => '100',
            'percentage' => '82',
            'national_rank' => '14',
            'grade' => 'A',
            'notification_title' => 'Important Olympiad Update',
            'notification_message' => 'This is a sample message from the admin notification center.',
            'support_email' => config('services.brevo.support_email') ?: config('mail.from.address'),
            'support_phone' => config('services.brevo.support_phone', '+91 72890 89009'),
            default => 'Sample',
        };
    }
}
