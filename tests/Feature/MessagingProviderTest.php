<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationBatch;
use App\Models\AdminRole;
use App\Models\ClassLevel;
use App\Models\MessagingProviderConfig;
use App\Models\MessagingSetting;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\Notifications\WhatsApp\Msg91WhatsAppGateway;
use App\Services\Notifications\WhatsApp\Msg91WhatsAppProvider;
use App\Services\Notifications\WhatsApp\WhatsAppProviderManager;
use App\Services\Notifications\WhatsApp\WhatsAppTemplateRegistry;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * WhatsApp provider switching (workflow.md §13.6): Bird stays the default and keeps
 * working; MSG91 is a second provider; the active one is chosen in Settings →
 * Messaging; broadcasts are pinned to the provider they were created with; each
 * provider's configuration and templates are independent. No real HTTP is made.
 */
class MessagingProviderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ClassLevel $class5;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
        $this->class5 = ClassLevel::create(['level' => 5, 'label' => 'Class 5', 'is_active' => true, 'sort_order' => 5]);
    }

    /* ─────────────────────────── default + Bird unchanged ─────────────────────────── */

    public function test_bird_is_the_default_and_broadcasts_and_deliveries_record_it(): void
    {
        $this->fakeBird();
        $this->student(['phone' => '9876543210']);

        $this->assertSame('bird', app(WhatsAppProviderManager::class)->activeKey());
        $this->sendWhatsApp(['template_id' => 'wat_exam', 'variables' => $this->birdVariables()])->assertSessionHasNoErrors();

        $log = NotificationLog::latest('id')->firstOrFail();
        $this->assertSame('bird', $log->provider);
        $this->assertSame('bird', $log->whatsapp_template['provider']);
        $this->assertSame('bird', NotificationDelivery::firstOrFail()->provider);
        $this->assertSame('sent', NotificationDelivery::firstOrFail()->status);
    }

    public function test_bird_broadcast_queued_before_a_switch_still_goes_through_bird(): void
    {
        Queue::fake();
        $this->fakeBird();
        $this->student(['phone' => '9876543210']);

        $this->sendWhatsApp(['template_id' => 'wat_exam', 'variables' => $this->birdVariables()])->assertRedirect();
        $log = NotificationLog::latest('id')->firstOrFail();
        $this->assertSame('pending', NotificationDelivery::firstOrFail()->status);

        $this->switchTo('msg91');
        Queue::assertPushed(DeliverNotificationBatch::class);
        app()->call([new DeliverNotificationBatch($log->id), 'handle']);

        $delivery = NotificationDelivery::firstOrFail();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('bird', $delivery->provider);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/whatsapp/messages'));
    }

    public function test_bird_status_sync_and_retry_keep_working_after_switching_to_msg91(): void
    {
        $this->fakeBird(Http::sequence()
            ->push(['code' => 'InvalidPayload', 'message' => 'Invalid recipient'], 422)
            ->push(['id' => 'wam_retry', 'status' => 'accepted'], 202), [
                'wam_retry' => ['id' => 'wam_retry', 'status' => 'delivered', 'delivered_at' => '2026-11-22T09:00:00Z'],
            ]);
        $this->student(['phone' => '9876543210']);
        $this->sendWhatsApp(['template_id' => 'wat_exam', 'variables' => $this->birdVariables()])->assertRedirect();
        $log = NotificationLog::latest('id')->firstOrFail();
        $this->assertSame('failed', NotificationDelivery::firstOrFail()->status);

        $this->switchTo('msg91');

        // Retry re-sends through Bird (the broadcast's provider), not MSG91.
        $this->actingAs($this->admin)->post(route('admin.notifications.retry', $log))->assertSessionHas('success');
        $this->assertSame('wam_retry', NotificationDelivery::firstOrFail()->provider_message_id);

        // Status sync still asks Bird for Bird messages.
        $this->artisan('notifications:maintain')->assertSuccessful();
        $this->assertSame('delivered', NotificationDelivery::firstOrFail()->status);

        $this->actingAs($this->admin)->get(route('admin.notifications.show', $log))
            ->assertInertia(fn (Assert $page) => $page->where('log.provider', 'bird')->where('canSyncStatus', true));
    }

    /* ─────────────────────────── switching ─────────────────────────── */

    public function test_switching_changes_the_composer_catalogue_and_blocks_sends_until_msg91_is_configured(): void
    {
        $this->fakeBird();
        $this->student(['phone' => '9876543210']);
        $template = $this->msg91Template();

        $this->switchTo('msg91')->assertSessionHas('success');
        $this->assertSame('msg91', MessagingSetting::current()->whatsapp_provider);
        $this->assertSame($this->admin->id, MessagingSetting::current()->updated_by);

        $this->actingAs($this->admin)->getJson(route('admin.notifications.whatsapp.templates'))
            ->assertOk()
            ->assertJsonPath('provider.key', 'msg91')
            ->assertJsonPath('template_source', 'registry')
            ->assertJsonPath('can_send', false)
            ->assertJsonCount(1, 'templates')
            ->assertJsonPath('templates.0.slug', 'exam_reminder');

        // Preview works (registry templates), sending is refused with the reason.
        $this->actingAs($this->admin)->postJson(route('admin.notifications.preview'), $this->payload(['provider' => 'msg91', 'template_id' => (string) $template->id, 'variables' => $this->msg91Variables()]))
            ->assertOk()->assertJsonPath('preview.body', 'Hi Aarav, your olympiad opens on Monday.');

        $this->sendWhatsApp(['provider' => 'msg91', 'template_id' => (string) $template->id, 'variables' => $this->msg91Variables()])
            ->assertSessionHasErrors('whatsapp.template_id');
        $this->assertSame(0, NotificationLog::count());
        Http::assertNothingSent();
    }

    public function test_a_draft_from_the_previous_provider_is_refused_after_a_switch(): void
    {
        $this->fakeBird();
        $this->student(['phone' => '9876543210']);
        $this->bindReadyMsg91();
        $this->switchTo('msg91');

        $this->sendWhatsApp(['provider' => 'bird', 'template_id' => 'wat_exam', 'variables' => $this->birdVariables()])
            ->assertSessionHasErrors(['whatsapp.template_id' => 'WhatsApp messages now go through MSG91 (the provider was switched in Settings → Messaging). Reload the page and pick a MSG91 template.']);
    }

    public function test_messages_route_through_msg91_when_it_is_active_and_the_log_is_provider_wise(): void
    {
        $sent = $this->bindReadyMsg91();
        $this->fakeBird();
        $student = $this->student(['name' => 'Aarav Sharma', 'phone' => '9876543210']);
        $template = $this->msg91Template();
        $this->switchTo('msg91');

        $this->sendWhatsApp(['provider' => 'msg91', 'template_id' => (string) $template->id, 'variables' => $this->msg91Variables()])
            ->assertSessionHasNoErrors();

        $log = NotificationLog::latest('id')->firstOrFail();
        $this->assertSame('msg91', $log->provider);
        $delivery = NotificationDelivery::firstOrFail();
        $this->assertSame('msg91', $delivery->provider);
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('msg91_1', $delivery->provider_message_id);
        $this->assertSame([['+919876543210', 'exam_reminder', 'en', ['1' => 'Aarav', '2' => 'Monday']]], $sent->all);
        Http::assertNothingSent(); // Bird was never called

        // A Bird broadcast in history, then filter History by provider.
        NotificationLog::create(['title' => 'Old Bird', 'message' => 'Hi', 'channel' => 'whatsapp', 'provider' => 'bird', 'audience' => 'all', 'status' => 'completed', 'sent_at' => now()]);
        $this->actingAs($this->admin)->get(route('admin.notifications', ['tab' => 'history', 'provider' => 'msg91']))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.provider', 'msg91')->where('logs.data.0.provider_label', 'MSG91'));
        $this->actingAs($this->admin)->get(route('admin.notifications', ['tab' => 'history', 'provider' => 'bird']))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.title', 'Old Bird'));

        $this->actingAs($this->admin)->get(route('admin.settings.messaging'))
            ->assertInertia(fn (Assert $page) => $page->where('providers.1.key', 'msg91')->where('providers.1.usage.sent_30d', 1)->where('providers.1.usage.broadcasts', 1)->where('providers.0.usage.broadcasts', 1));
        $this->assertSame($student->id, $delivery->user_id);
    }

    public function test_unknown_providers_cannot_be_selected(): void
    {
        $this->switchTo('twilio')->assertSessionHasErrors('provider');
        $this->assertSame('bird', MessagingSetting::current()->whatsapp_provider);
    }

    /* ─────────────────────────── configuration ─────────────────────────── */

    public function test_saved_credentials_are_encrypted_override_env_and_never_reach_the_browser(): void
    {
        config(['services.bird.api_key' => 'env-bird-key', 'services.bird.base_url' => 'https://bird.test']);

        $this->actingAs($this->admin)->put(route('admin.settings.messaging.providers.update', 'bird'), [
            'api_key' => 'db-bird-secret-1234', 'base_url' => 'https://bird.test/', 'whatsapp_from' => '+919990000000',
        ])->assertSessionHas('success');

        $raw = DB::table('messaging_provider_configs')->where('provider', 'bird')->value('credentials');
        $this->assertStringNotContainsString('db-bird-secret-1234', (string) $raw);
        $this->assertSame('https://bird.test', MessagingProviderConfig::where('provider', 'bird')->first()->settings['base_url']);

        // The saved key is what Bird receives.
        Http::fake(['bird.test/v1/whatsapp/templates*' => Http::response(['data' => [], 'next_cursor' => null])]);
        $this->actingAs($this->admin)->postJson(route('admin.settings.messaging.providers.check', 'bird'))->assertOk()->assertJsonPath('ok', true);
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer db-bird-secret-1234'));
        $this->assertTrue(MessagingProviderConfig::where('provider', 'bird')->first()->last_check_ok);

        // The page shows only a mask, and where each value comes from.
        $response = $this->actingAs($this->admin)->get(route('admin.settings.messaging'));
        $this->assertStringNotContainsString('db-bird-secret-1234', $response->getContent());
        $this->assertStringNotContainsString('env-bird-key', $response->getContent());
        $response->assertInertia(fn (Assert $page) => $page
            ->where('providers.0.config.fields.0.source', 'database')
            ->where('providers.0.config.fields.0.masked', '••••••1234')
            ->where('providers.0.config.fields.0.value', null)
            ->where('providers.1.config.fields.0.source', 'none'));

        // Blank secret keeps the saved key; "clear" falls back to .env.
        $this->actingAs($this->admin)->put(route('admin.settings.messaging.providers.update', 'bird'), ['api_key' => '', 'base_url' => 'https://bird.test'])->assertSessionHas('success');
        $this->assertSame('db-bird-secret-1234', MessagingProviderConfig::where('provider', 'bird')->first()->credentials['api_key']);

        $this->actingAs($this->admin)->put(route('admin.settings.messaging.providers.update', 'bird'), ['base_url' => 'https://bird.test', 'clear' => ['api_key' => true]])->assertSessionHas('success');
        $this->assertNull(MessagingProviderConfig::where('provider', 'bird')->first()->credentials);
        $this->assertSame('env-bird-key', config('services.bird.api_key'));
    }

    public function test_each_provider_keeps_its_own_configuration(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.messaging.providers.update', 'msg91'), [
            'auth_key' => 'msg91-auth-secret', 'base_url' => 'https://msg91.test', 'integrated_number' => '+919990000001',
        ])->assertSessionHas('success');
        $this->actingAs($this->admin)->put(route('admin.settings.messaging.providers.update', 'bird'), [
            'api_key' => 'bird-secret-key', 'base_url' => 'https://bird.test',
        ])->assertSessionHas('success');

        $this->switchTo('msg91');
        $this->switchTo('bird');

        $this->assertSame('msg91-auth-secret', MessagingProviderConfig::where('provider', 'msg91')->first()->credentials['auth_key']);
        $this->assertSame('+919990000001', MessagingProviderConfig::where('provider', 'msg91')->first()->settings['integrated_number']);
        $this->assertSame('bird-secret-key', MessagingProviderConfig::where('provider', 'bird')->first()->credentials['api_key']);

        // MSG91's own saved settings are what its connection check uses.
        Http::fake(['msg91.test/api/v5/whatsapp/whatsapp-activation/' => Http::response(['data' => [['integrated_number' => '919990000001']]])]);
        $msg91 = app(WhatsAppProviderManager::class)->provider('msg91');
        $this->assertTrue($msg91->isConfigured());
        $this->assertTrue($msg91->canSend());
        $this->actingAs($this->admin)->postJson(route('admin.settings.messaging.providers.check', 'msg91'))
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('message', 'Connected to MSG91. The integrated number is active on this account.');
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://msg91.test/api/v5/whatsapp/whatsapp-activation/' && $r->hasHeader('authkey', 'msg91-auth-secret'));
    }

    public function test_only_the_super_admin_edits_credentials_and_roles_follow_the_matrix(): void
    {
        $editor = $this->staff(['settings_messaging' => ['read' => true, 'write' => true, 'delete' => false]]);
        $outsider = $this->staff(['notifications' => ['read' => true, 'write' => false, 'delete' => false]]);

        $this->actingAs($editor)->get(route('admin.settings.messaging'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.write', true)->where('can.credentials', false)->where('can.delete', false));
        $this->actingAs($editor)->put(route('admin.settings.messaging.providers.update', 'bird'), ['api_key' => 'x'])->assertForbidden();
        $this->assertSame(0, MessagingProviderConfig::count());
        $this->actingAs($editor)->put(route('admin.settings.messaging.switch'), ['provider' => 'msg91'])->assertSessionHas('success');

        $this->actingAs($outsider)->get(route('admin.settings.messaging'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($outsider)->put(route('admin.settings.messaging.switch'), ['provider' => 'bird'])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('msg91', MessagingSetting::current()->whatsapp_provider);
    }

    /* ─────────────────────────── templates ─────────────────────────── */

    public function test_msg91_templates_are_managed_locally_and_scoped_to_their_provider(): void
    {
        $store = fn (array $data) => $this->actingAs($this->admin)->post(route('admin.settings.messaging.templates.store', 'msg91'), array_merge([
            'name' => 'exam_reminder', 'language' => 'en', 'category' => 'utility', 'approval_status' => 'approved',
            'body' => 'Hi {{1}}, your olympiad opens on {{2}}.', 'variable_examples' => ['1' => 'Aarav', '2' => 'Monday', '9' => 'dropped'], 'is_active' => true,
        ], $data));

        $store([])->assertSessionHas('success');
        $store([])->assertSessionHasErrors('name');               // same name + language
        $store(['language' => 'hi'])->assertSessionHas('success'); // another language is fine
        $store(['name' => 'Bad Name'])->assertSessionHasErrors('name');

        $template = WhatsAppTemplate::where('language', 'en')->firstOrFail();
        $this->assertSame('msg91', $template->provider);
        $this->assertSame(['1' => 'Aarav', '2' => 'Monday'], $template->variable_examples);

        // Bird templates are live: nothing can be stored for Bird.
        $this->actingAs($this->admin)->post(route('admin.settings.messaging.templates.store', 'bird'), ['name' => 'x', 'language' => 'en', 'category' => 'utility', 'approval_status' => 'approved', 'body' => 'x'])
            ->assertStatus(422);

        $registry = app(WhatsAppTemplateRegistry::class);
        $this->assertCount(2, $registry->list('msg91'));
        $this->assertCount(0, $registry->list('bird'));
        $detail = $registry->find('msg91', (string) $template->id);
        $this->assertTrue($detail['usable']);
        $this->assertSame(['1', '2'], array_column($detail['variables'], 'key'));
        $this->assertNull($registry->find('bird', (string) $template->id), 'one provider never sees another provider’s templates');

        // Pending or OTP templates aren't usable for broadcasts.
        $this->actingAs($this->admin)->put(route('admin.settings.messaging.templates.update', $template), [
            'name' => 'exam_reminder', 'language' => 'en', 'category' => 'utility', 'approval_status' => 'pending', 'body' => 'Hi {{1}}',
        ])->assertSessionHas('success');
        $this->assertFalse($registry->find('msg91', (string) $template->id)['usable']);

        $this->actingAs($this->admin)->delete(route('admin.settings.messaging.templates.destroy', $template))->assertSessionHas('success');
        $this->assertSame(1, WhatsAppTemplate::count());
    }

    public function test_settings_page_lists_bird_templates_live_and_sends_a_bird_test_message(): void
    {
        $this->fakeBird();

        $this->actingAs($this->admin)->getJson(route('admin.settings.messaging.providers.templates', 'bird'))
            ->assertOk()->assertJsonCount(2, 'templates')->assertJsonPath('templates.0.slug', 'exam-update');

        $this->actingAs($this->admin)->postJson(route('admin.settings.messaging.providers.test', 'bird'), [
            'template_id' => 'wat_exam', 'to' => '9876543210',
            'variables' => ['variable_1' => 'Aarav', 'variable_2' => "Maths\nOlympiad"],
        ])->assertStatus(422)->assertJsonValidationErrors('variables.variable_3');

        $this->actingAs($this->admin)->postJson(route('admin.settings.messaging.providers.test', 'bird'), [
            'template_id' => 'wat_exam', 'to' => '9876543210',
            'variables' => ['variable_1' => 'Aarav', 'variable_2' => "Maths\nOlympiad", 'variable_3' => 'Monday'],
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r['to'] === '+919876543210'
            && collect($r['template']['components'][0]['parameters'])->firstWhere('name', 'variable_2')['text'] === 'Maths Olympiad');
        $this->assertSame(0, NotificationLog::count(), 'a test is not a broadcast');
    }

    /* ─────────────────────────── helpers ─────────────────────────── */

    private function switchTo(string $provider)
    {
        return $this->actingAs($this->admin)->put(route('admin.settings.messaging.switch'), ['provider' => $provider]);
    }

    private function payload(array $whatsapp): array
    {
        return [
            'channel' => 'whatsapp',
            'audience' => ['mode' => 'filters'],
            'whatsapp' => $whatsapp,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    private function sendWhatsApp(array $whatsapp)
    {
        return $this->actingAs($this->admin)->post(route('admin.notifications.send'), $this->payload($whatsapp));
    }

    private function birdVariables(): array
    {
        return [
            'variable_1' => ['source' => 'placeholder', 'value' => 'student_name'],
            'variable_2' => ['source' => 'text', 'value' => 'Olympiad'],
            'variable_3' => ['source' => 'text', 'value' => 'Monday'],
        ];
    }

    private function msg91Variables(): array
    {
        return [
            '1' => ['source' => 'placeholder', 'value' => 'first_name'],
            '2' => ['source' => 'text', 'value' => 'Monday'],
        ];
    }

    private function msg91Template(): WhatsAppTemplate
    {
        return WhatsAppTemplate::create([
            'provider' => 'msg91', 'name' => 'exam_reminder', 'language' => 'en', 'category' => 'utility',
            'body' => 'Hi {{1}}, your olympiad opens on {{2}}.', 'approval_status' => 'approved', 'is_active' => true,
        ]);
    }

    /**
     * Stands in for a ready MSG91: configured, can send, records every send. Proves
     * routing independently of the HTTP payload (Msg91WhatsAppTest covers that).
     */
    private function bindReadyMsg91(): object
    {
        $recorder = new class
        {
            public array $all = [];
        };

        $this->app->bind(Msg91WhatsAppProvider::class, fn ($app) => new class($app->make(Msg91WhatsAppGateway::class), $app->make(WhatsAppTemplateRegistry::class), $recorder) extends Msg91WhatsAppProvider
        {
            public function __construct(Msg91WhatsAppGateway $gateway, WhatsAppTemplateRegistry $registry, private object $recorder)
            {
                parent::__construct($gateway, $registry);
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function canSend(): bool
            {
                return true;
            }

            public function send(string $to, array $template, array $variables): array
            {
                $this->recorder->all[] = [$to, $template['slug'], $template['language'], $variables];

                return ['id' => 'msg91_'.count($this->recorder->all), 'status' => 'accepted'];
            }
        });

        return $recorder;
    }

    private function staff(array $permissions): User
    {
        $role = AdminRole::create(['name' => 'Role '.Str::random(4), 'is_active' => true]);
        foreach (AdminPermissions::normalizeMatrix($permissions) as $module => $a) {
            $role->permissions()->create(['module' => $module, 'can_read' => $a['read'], 'can_write' => $a['write'], 'can_delete' => $a['delete']]);
        }

        return User::factory()->create(['role' => 'admin', 'admin_role_id' => $role->id, 'is_active' => true]);
    }

    private function student(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'student', 'is_active' => true, 'email_verified_at' => now(),
            'name' => 'Aarav Sharma', 'class_level_id' => $this->class5->id,
        ], $overrides));
    }

    /** Same Bird fakes as AdminNotificationBroadcastTest. */
    private function fakeBird($send = null, array $messages = []): void
    {
        config([
            'services.bird.api_key' => 'bird-test-key',
            'services.bird.base_url' => 'https://bird.test',
            'services.bird.whatsapp_from' => '+919990000000',
            'services.bird.send_interval_ms' => 0,
        ]);

        $fakes = [
            'bird.test/v1/whatsapp/templates/wat_exam/versions/*' => Http::response([
                'id' => 'wav_exam_1', 'version_number' => 1,
                'variables' => [
                    ['key' => 'variable_1', 'required' => true, 'type' => 'text'],
                    ['key' => 'variable_2', 'required' => true, 'type' => 'text'],
                    ['key' => 'variable_3', 'required' => true, 'type' => 'text'],
                ],
                'languages' => ['en' => ['status' => 'approved', 'components' => [
                    ['type' => 'body', 'text' => 'Hi {{variable_1}}, {{variable_2}} opens on {{variable_3}}.'],
                ]]],
            ]),
            'bird.test/v1/whatsapp/templates*' => Http::response(['data' => [
                ['id' => 'wat_otp', 'slug' => 'bird_otp', 'name' => 'One-time passcode', 'category' => 'authentication', 'status' => 'active', 'default_language' => 'en', 'live_version_id' => 'wav_otp', 'languages' => ['en' => ['status' => 'approved']]],
                ['id' => 'wat_exam', 'slug' => 'exam-update', 'name' => 'Exam update', 'category' => 'utility', 'status' => 'active', 'default_language' => 'en', 'live_version_id' => 'wav_exam_1', 'languages' => ['en' => ['status' => 'approved']]],
            ], 'next_cursor' => null]),
        ];

        foreach ($messages as $id => $body) {
            $fakes["bird.test/v1/whatsapp/messages/{$id}"] = Http::response($body);
        }

        $counter = 0;
        $fakes['bird.test/v1/whatsapp/messages'] = $send ?? function () use (&$counter) {
            $counter++;

            return Http::response(['id' => 'wam_'.$counter, 'status' => 'accepted'], 202);
        };

        Http::fake($fakes);
    }
}
