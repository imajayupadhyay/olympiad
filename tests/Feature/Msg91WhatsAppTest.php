<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\Notifications\NotificationBroadcastService;
use App\Services\Notifications\WhatsApp\Msg91Exception;
use App\Services\Notifications\WhatsApp\Msg91WhatsAppGateway;
use App\Services\Notifications\WhatsApp\WhatsAppProviderManager;
use App\Services\Notifications\WhatsApp\WhatsAppTemplateRegistry;
use App\Services\Notifications\WhatsAppNotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * MSG91's HTTP adapter against the endpoints in MSG91's published OpenAPI specs
 * (workflow.md §13.6). All HTTP is faked; nothing reaches MSG91.
 */
class Msg91WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.msg91.auth_key' => 'msg91-test-key',
            'services.msg91.base_url' => 'https://msg91.test',
            'services.msg91.integrated_number' => '+91 99900 00001',
            'services.msg91.send_interval_ms' => 0,
        ]);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_a_broadcast_sends_the_documented_template_payload_and_stores_the_request_id(): void
    {
        Http::fake(['msg91.test/api/v5/whatsapp/whatsapp-outbound-message/bulk/' => Http::response([
            'status' => 'success', 'hasError' => false, 'data' => 'Your request is in process, check delivery reports for status', 'errors' => null, 'request_id' => 'req_abc123',
        ])]);
        $class = ClassLevel::create(['level' => 5, 'label' => 'Class 5', 'is_active' => true, 'sort_order' => 5]);
        User::factory()->create(['role' => 'student', 'is_active' => true, 'email_verified_at' => now(), 'name' => 'Aarav Sharma', 'phone' => '9876543210', 'class_level_id' => $class->id]);
        $template = $this->template();
        $this->actingAs($this->admin)->put(route('admin.settings.messaging.switch'), ['provider' => 'msg91']);

        $this->actingAs($this->admin)->post(route('admin.notifications.send'), [
            'channel' => 'whatsapp',
            'audience' => ['mode' => 'filters'],
            'whatsapp' => ['provider' => 'msg91', 'template_id' => (string) $template->id, 'variables' => [
                '1' => ['source' => 'placeholder', 'value' => 'first_name'],
                '2' => ['source' => 'text', 'value' => 'Monday'],
            ]],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST'
            && $r->url() === 'https://msg91.test/api/v5/whatsapp/whatsapp-outbound-message/bulk/'
            && $r->hasHeader('authkey', 'msg91-test-key')
            && $r->data() === [
                'integrated_number' => '919990000001',
                'content_type' => 'template',
                'payload' => [
                    'messaging_product' => 'whatsapp',
                    'type' => 'template',
                    'template' => [
                        'name' => 'exam_reminder',
                        'language' => ['code' => 'en', 'policy' => 'deterministic'],
                        'to_and_components' => [[
                            'to' => ['919876543210'],
                            'components' => [
                                'body_1' => ['type' => 'text', 'value' => 'Aarav'],
                                'body_2' => ['type' => 'text', 'value' => 'Monday'],
                            ],
                        ]],
                    ],
                ],
            ]);

        $delivery = NotificationDelivery::firstOrFail();
        $this->assertSame('msg91', $delivery->provider);
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('req_abc123', $delivery->provider_message_id);
        $this->assertSame('msg91', NotificationLog::firstOrFail()->provider);
    }

    public function test_named_variables_are_sent_as_the_body_components_msg91_lists(): void
    {
        Http::fake(['msg91.test/*' => Http::response(['status' => 'success', 'hasError' => false, 'request_id' => 'req_named'])]);

        $result = app(Msg91WhatsAppGateway::class)->send('+919876543210', 'exam_notification', 'en', ['student_name' => 'Aarav', 'exam_date' => '5th December']);

        $this->assertSame(['id' => 'req_named', 'status' => 'accepted'], $result);
        Http::assertSent(fn (HttpRequest $r) => $r['payload']['template']['to_and_components'][0]['components'] === [
            'body_student_name' => ['type' => 'text', 'value' => 'Aarav'],
            'body_exam_date' => ['type' => 'text', 'value' => '5th December'],
        ]);
    }

    public function test_errors_rate_limits_and_has_error_responses_are_raised(): void
    {
        $gateway = app(Msg91WhatsAppGateway::class);

        Http::fake(['msg91.test/*' => Http::sequence()
            ->push(['status' => 'fail', 'hasError' => true, 'errors' => 'Template not found', 'code' => '301'], 200)
            ->push(['message' => 'Too many'], 429, ['Retry-After' => '12'])
            ->push(['status' => 'fail', 'hasError' => true, 'errors' => 'Unauthorized', 'code' => '401'], 401)
            ->push(['status' => 'success', 'hasError' => false], 200)]);

        $e = $this->catch(fn () => $gateway->send('+919876543210', 'missing', 'en', ['1' => 'x']));
        $this->assertSame('MSG91 HTTP 200 — Template not found', $e->getMessage());
        $this->assertSame('301', $e->code_);
        $this->assertFalse($e->isTransient());

        $e = $this->catch(fn () => $gateway->send('+919876543210', 'exam_reminder', 'en'));
        $this->assertTrue($e->isRateLimited());
        $this->assertSame(12, $e->retryAfter);

        $e = $this->catch(fn () => $gateway->numbers());
        $this->assertSame(401, $e->status);

        $e = $this->catch(fn () => $gateway->send('+919876543210', 'exam_reminder', 'en'));
        $this->assertSame('MSG91 accepted the request but returned no request_id.', $e->getMessage());
    }

    public function test_a_failed_send_marks_the_delivery_failed(): void
    {
        Http::fake(['msg91.test/*' => Http::response(['status' => 'fail', 'hasError' => true, 'errors' => 'Invalid template'], 400)]);
        $student = User::factory()->create(['role' => 'student', 'phone' => '9876543210']);
        $log = NotificationLog::create(['title' => 'T', 'message' => 'Hi', 'channel' => 'whatsapp', 'provider' => 'msg91', 'audience' => 'all', 'status' => 'sending',
            'whatsapp_template' => ['provider' => 'msg91', 'slug' => 'exam_reminder', 'language' => 'en', 'body' => 'Hi', 'variables' => []]]);
        $delivery = NotificationDelivery::create(['notification_log_id' => $log->id, 'user_id' => $student->id, 'channel' => 'whatsapp', 'status' => 'sending']);

        app(WhatsAppNotificationChannel::class)->deliver($delivery, $log, $student, null);

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('MSG91 HTTP 400 — Invalid template', $delivery->error);
        $this->assertSame('http_400', $delivery->error_code);
    }

    public function test_template_sync_adds_and_updates_without_deleting_or_overwriting_admin_fields(): void
    {
        $manual = WhatsAppTemplate::create(['provider' => 'msg91', 'name' => 'hand_made', 'language' => 'en', 'category' => 'utility', 'body' => 'Hi', 'approval_status' => 'approved', 'is_active' => true, 'source' => 'manual']);
        $existing = WhatsAppTemplate::create(['provider' => 'msg91', 'name' => 'exam_reminder', 'language' => 'en', 'category' => 'utility', 'body' => 'Old', 'approval_status' => 'pending',
            'is_active' => false, 'display_name' => 'Exam reminder', 'variable_examples' => ['1' => 'Riya'], 'source' => 'manual']);
        $bird = WhatsAppTemplate::create(['provider' => 'bird', 'name' => 'exam_reminder', 'language' => 'en', 'category' => 'utility', 'body' => 'Bird', 'approval_status' => 'approved', 'is_active' => true]);

        Http::fake(['msg91.test/api/v5/whatsapp/get-template-client/919990000001*' => Http::response(['status' => 'success', 'data' => [
            // MSG91's real shape (seen 2026-11-22): languages[] with `code` components, named params.
            ['name' => 'exam_reminder', 'category' => 'UTILITY', 'namespace' => 'ns_1', 'languages' => [
                ['id' => '111', 'msg91_template_id' => 621026, 'name' => 'exam_reminder', 'language' => 'en', 'parameter_format' => 'NAMED', 'status' => 'approved',
                    'variables' => ['body_student_name', 'body_exam_date'], 'is_disabled' => 0, 'code' => [
                        ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Exam notice'],
                        ['type' => 'BODY', 'text' => 'Hi {{student_name}}, your olympiad opens on {{exam_date}}.', 'example' => ['body_text_named_params' => [
                            ['param_name' => 'student_name', 'example' => 'Vikas'], ['param_name' => 'exam_date', 'example' => '5th December'],
                        ]]],
                        ['type' => 'FOOTER', 'text' => 'Team'],
                    ]],
                ['language' => 'hi', 'status' => 'pending', 'id' => '112', 'code' => [['type' => 'BODY', 'text' => 'नमस्ते {{1}}', 'example' => ['body_text' => [['Riya']]]]]],
                ['language' => 'mr', 'status' => 'approved', 'is_disabled' => 1, 'id' => '114', 'code' => [['type' => 'BODY', 'text' => 'नमस्कार']]],
            ]],
            // Meta's one-language shape, with a media header.
            ['name' => 'result_card', 'language' => 'en_US', 'status' => 'APPROVED', 'category' => 'MARKETING', 'id' => '113', 'components' => [
                ['type' => 'HEADER', 'format' => 'IMAGE'],
                ['type' => 'BODY', 'text' => 'Your result is ready.', 'example' => ['body_text' => [[]]]],
                ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'text' => 'View', 'url' => 'https://example.test/{{1}}']]],
            ]],
            ['name' => 'Bad Name!', 'language' => 'en', 'components' => [['type' => 'BODY', 'text' => 'x']]],
        ]])]);

        $this->actingAs($this->admin)->post(route('admin.settings.messaging.providers.templates.sync', 'msg91'))
            ->assertSessionHas('success', 'Synced 4 template(s) from MSG91.');

        $existing->refresh();
        $this->assertSame('Hi {{student_name}}, your olympiad opens on {{exam_date}}.', $existing->body);
        $this->assertSame('Exam notice', $existing->header);
        $this->assertSame(['body_student_name', 'body_exam_date'], $existing->meta['msg91_variables']);
        $this->assertSame('approved', $existing->approval_status);
        $this->assertSame('111', $existing->external_id);
        $this->assertSame('Team', $existing->footer);
        $this->assertSame('Exam reminder', $existing->display_name);
        $this->assertSame(['1' => 'Riya'], $existing->variable_examples);
        $this->assertFalse($existing->is_active);

        $hi = WhatsAppTemplate::forProvider('msg91')->where('name', 'exam_reminder')->where('language', 'hi')->firstOrFail();
        $this->assertSame('pending', $hi->approval_status);
        $this->assertSame('synced', $hi->source);
        $this->assertSame(['1' => 'Riya'], $hi->variable_examples);
        $this->assertSame('rejected', WhatsAppTemplate::forProvider('msg91')->where('language', 'mr')->value('approval_status'));

        $registry = app(WhatsAppTemplateRegistry::class);
        $card = WhatsAppTemplate::forProvider('msg91')->where('name', 'result_card')->firstOrFail();
        $this->assertSame('marketing', $card->category);
        $this->assertSame('Has a media header (image), which broadcasts can’t fill.', $registry->find('msg91', (string) $card->id)['unusable_reason']);

        $this->assertNotNull($manual->fresh());
        $this->assertSame('Bird', $bird->fresh()->body);
        $this->assertSame(5, WhatsAppTemplate::forProvider('msg91')->count()); // hand_made, exam_reminder en + hi + mr, result_card
    }

    public function test_an_unreadable_template_list_is_reported_not_guessed(): void
    {
        Http::fake(['msg91.test/*' => Http::response(['data' => [['foo' => 'bar']]])]);

        $this->actingAs($this->admin)->post(route('admin.settings.messaging.providers.templates.sync', 'msg91'))
            ->assertSessionHas('error', 'Template sync failed: MSG91 returned 1 template(s), but none were in a format this page can read. Add them by hand instead.');
        $this->assertSame(0, WhatsAppTemplate::count());
    }

    public function test_status_polling_is_off_by_default_and_reads_the_delivery_log_when_enabled(): void
    {
        $providers = app(WhatsAppProviderManager::class);
        $this->assertFalse($providers->provider('msg91')->supportsStatusSync());

        config(['services.msg91.status_polling' => true]);
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(12, 0));
        Http::fake(['msg91.test/api/v5/report/logs/wa*' => Http::sequence()
            ->push(['data' => [['requestId' => 'req_1', 'status' => 'delivered', 'updated_at' => '2026-11-22T06:00:00Z']]])
            ->push(['data' => [['requestId' => 'req_2', 'status' => 'failed', 'failureReason' => 'Number not on WhatsApp', 'errorCode' => '131026']]])
            ->push(['data' => [['requestId' => 'req_3', 'something' => 'unknown']]])]);

        $log = NotificationLog::create(['title' => 'T', 'message' => 'Hi', 'channel' => 'whatsapp', 'provider' => 'msg91', 'audience' => 'all', 'status' => 'completed']);
        $make = fn (string $id) => NotificationDelivery::create(['notification_log_id' => $log->id, 'user_id' => User::factory()->create(['role' => 'student'])->id, 'channel' => 'whatsapp', 'provider' => 'msg91',
            'status' => 'sent', 'provider_message_id' => $id, 'sent_at' => now()->subHour()]);
        [$a, $b, $c] = [$make('req_1'), $make('req_2'), $make('req_3')];

        $this->assertSame(3, app(NotificationBroadcastService::class)->syncWhatsAppStatuses(app(WhatsAppNotificationChannel::class), $log));

        $this->assertSame('delivered', $a->fresh()->status);
        $this->assertSame('2026-11-22 06:00:00', $a->fresh()->delivered_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('failed', $b->fresh()->status);
        $this->assertSame('Number not on WhatsApp', $b->fresh()->error);
        $this->assertTrue((bool) $b->fresh()->permanent_failure);
        $this->assertSame('sent', $c->fresh()->status);
        $this->assertNotNull($c->fresh()->status_checked_at);

        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://msg91.test/api/v5/report/logs/wa?')
            && $r['requestId'] === 'req_1' && $r['startDate'] === '2026-11-20' && $r['endDate'] === '2026-11-22');
    }

    public function test_connection_check_reports_a_missing_number_and_a_rejected_key(): void
    {
        Http::fake(['msg91.test/api/v5/whatsapp/whatsapp-activation/' => Http::sequence()
            ->push(['data' => [['integrated_number' => '918888800000']]])
            ->push(['status' => 'fail', 'hasError' => true, 'errors' => 'Unauthorized', 'code' => '401'], 401)]);
        $check = fn () => $this->actingAs($this->admin)->postJson(route('admin.settings.messaging.providers.check', 'msg91'))->assertOk();

        $check()->assertJsonPath('ok', true)->assertJsonPath('message', fn ($m) => str_contains($m, 'wasn’t found'));
        $check()->assertJsonPath('ok', false)->assertJsonPath('message', 'Could not reach MSG91: MSG91 HTTP 401 — Unauthorized');
    }

    private function template(): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'provider' => 'msg91', 'name' => 'exam_reminder', 'language' => 'en', 'category' => 'utility',
            'body' => 'Hi {{1}}, your olympiad opens on {{2}}.', 'approval_status' => 'approved', 'is_active' => true,
        ]);
    }

    private function catch(callable $call): Msg91Exception
    {
        try {
            $call();
        } catch (Msg91Exception $e) {
            return $e;
        }
        $this->fail('Expected Msg91Exception.');
    }
}
