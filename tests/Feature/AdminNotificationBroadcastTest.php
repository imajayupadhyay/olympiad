<?php

namespace Tests\Feature;

use App\Jobs\SendManagedEmail;
use App\Models\EmailLog;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminNotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_paid_and_unpaid_broadcast_recipients(): void
    {
        $admin = $this->admin();
        $paidStudent = $this->student(['email' => 'paid@example.com']);
        $unpaidStudent = $this->student(['email' => 'unpaid@example.com']);

        $this->paidPaymentFor($paidStudent);

        $payload = $this->broadcastPayload(['payment_status' => 'paid']);

        $this->actingAs($admin)
            ->postJson(route('admin.notifications.preview'), $payload)
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('sample.0.email', 'paid@example.com');

        $this->actingAs($admin)
            ->postJson(route('admin.notifications.preview'), array_merge($payload, ['payment_status' => 'unpaid']))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('sample.0.email', 'unpaid@example.com');

        $this->assertSame(2, User::where('role', 'student')->count());
        $this->assertTrue($unpaidStudent->exists);
    }

    public function test_admin_broadcast_email_queues_only_matching_filtered_students(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $paidStudent = $this->student(['email' => 'paid@example.com']);
        $unpaidStudent = $this->student(['email' => 'unpaid@example.com']);

        $this->paidPaymentFor($paidStudent);

        $this->actingAs($admin)
            ->post(route('admin.notifications.send'), $this->broadcastPayload([
                'payment_status' => 'unpaid',
                'title' => 'Complete your olympiad registration',
                'message' => 'Your olympiad payment is still pending.',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $log = NotificationLog::firstOrFail();

        $this->assertSame(1, $log->recipient_count);
        $this->assertSame('unpaid', $log->audience_filters['payment_status']);

        $this->assertDatabaseHas('email_logs', [
            'template_key' => 'notification_blast',
            'recipient_user_id' => $unpaidStudent->id,
            'recipient_email' => 'unpaid@example.com',
        ]);

        $this->assertDatabaseMissing('email_logs', [
            'recipient_user_id' => $paidStudent->id,
            'recipient_email' => 'paid@example.com',
        ]);

        Queue::assertPushed(SendManagedEmail::class);
        $this->assertSame(1, EmailLog::count());
    }

    public function test_admin_can_search_and_broadcast_to_selected_students(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $selected = $this->student(['name' => 'Selected Student', 'email' => 'selected@example.com']);
        $other = $this->student(['name' => 'Other Student', 'email' => 'other@example.com']);

        $this->actingAs($admin)
            ->getJson(route('admin.notifications.students', ['search' => 'selected@example.com']))
            ->assertOk()
            ->assertJsonPath('students.0.id', $selected->id)
            ->assertJsonPath('students.0.email', 'selected@example.com');

        $payload = $this->broadcastPayload([
            'recipient_mode' => 'selected',
            'selected_user_ids' => [$selected->id],
            'payment_status' => 'paid',
            'title' => 'Direct student mail',
            'message' => 'This should only go to the selected student.',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.notifications.preview'), $payload)
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('sample.0.email', 'selected@example.com');

        $this->actingAs($admin)
            ->post(route('admin.notifications.send'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $log = NotificationLog::latest()->firstOrFail();

        $this->assertSame(1, $log->recipient_count);
        $this->assertSame('selected', $log->audience_filters['recipient_mode']);
        $this->assertSame([$selected->id], $log->audience_filters['selected_user_ids']);

        $this->assertDatabaseHas('email_logs', [
            'recipient_user_id' => $selected->id,
            'recipient_email' => 'selected@example.com',
        ]);

        $this->assertDatabaseMissing('email_logs', [
            'recipient_user_id' => $other->id,
            'recipient_email' => 'other@example.com',
        ]);
    }

    private function broadcastPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Important Olympiad Update',
            'message' => 'This is a filtered broadcast message.',
            'channel' => 'email',
            'recipient_mode' => 'filters',
            'selected_user_ids' => [],
            'audience' => 'all',
            'exam_id' => '',
            'class_level_id' => '',
            'student_status' => 'active',
            'payment_status' => 'all',
            'enrollment_status' => 'all',
            'state' => '',
            'search' => '',
        ], $overrides);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function student(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function paidPaymentFor(User $student): Payment
    {
        return Payment::create([
            'user_id' => $student->id,
            'amount' => 499,
            'gross_amount' => 499,
            'discount_amount' => 0,
            'currency' => 'INR',
            'status' => 'paid',
            'gateway' => 'razorpay',
            'razorpay_order_id' => 'order_'.uniqid(),
            'razorpay_payment_id' => 'pay_'.uniqid(),
            'paid_at' => now(),
        ]);
    }
}
