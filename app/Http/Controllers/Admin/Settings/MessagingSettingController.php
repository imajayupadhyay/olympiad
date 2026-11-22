<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\MessagingSetting;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\WhatsAppTemplate;
use App\Services\Notifications\WhatsApp\MessagingConfigRepository;
use App\Services\Notifications\WhatsApp\WhatsAppProvider;
use App\Services\Notifications\WhatsApp\WhatsAppProviderException;
use App\Services\Notifications\WhatsApp\WhatsAppProviderManager;
use App\Services\Notifications\WhatsApp\WhatsAppTemplateRegistry;
use App\Services\PhoneNumberService;
use App\Support\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Messaging (workflow.md §13.6): the active WhatsApp provider, each
 * provider's configuration (credentials: super admin only), connection checks,
 * test messages, locally managed templates and provider-wise usage. Thin: rules
 * live in WhatsAppProviderManager, MessagingConfigRepository and WhatsAppTemplateRegistry.
 */
class MessagingSettingController extends Controller
{
    public function __construct(
        private readonly WhatsAppProviderManager $providers,
        private readonly MessagingConfigRepository $configs,
        private readonly WhatsAppTemplateRegistry $registry,
        private readonly PhoneNumberService $phones,
    ) {}

    public function index(Request $request): Response
    {
        $setting = MessagingSetting::current()->load('updatedBy:id,name');
        $user = $request->user();
        $usage = $this->usage();

        return Inertia::render('Admin/Settings/Messaging/Index', [
            'active' => [
                'key' => $this->providers->activeKey(),
                'switched_at' => $setting->switched_at,
                'switched_by' => $setting->updatedBy?->name,
            ],
            'providers' => collect($this->providers->all())->map(fn (WhatsAppProvider $p) => [
                'key' => $p->key(),
                'label' => $p->label(),
                'configured' => $p->isConfigured(),
                'can_send' => $p->canSend(),
                'unavailable_reason' => $p->unavailableReason(),
                'template_source' => $p->templateSource(),
                'can_sync_templates' => $p->canSyncTemplates(),
                'supports_status_sync' => $p->supportsStatusSync(),
                'sender' => $p->sender() ? $this->phones->mask($p->sender()) : null,
                'config' => $this->configs->describe($p->key()),
                'last_check' => $this->configs->lastCheck($p->key()),
                'usage' => $usage[$p->key()] ?? $this->emptyUsage(),
                'templates' => $p->templateSource() === 'registry' ? $this->registry->rows($p->key()) : null,
            ])->values(),
            'templateOptions' => [
                'categories' => WhatsAppTemplate::CATEGORIES,
                'approvalStatuses' => WhatsAppTemplate::APPROVAL_STATUSES,
            ],
            'can' => [
                'write' => AdminPermissions::allows($user, 'settings_messaging', 'write'),
                'delete' => AdminPermissions::allows($user, 'settings_messaging', 'delete'),
                'credentials' => $user->isSuperAdmin(),
                'notifications' => AdminPermissions::allows($user, 'notifications', 'read'),
            ],
        ]);
    }

    /** Which provider sends NEW WhatsApp broadcasts. Sent and queued broadcasts keep theirs. */
    public function switch(Request $request): RedirectResponse
    {
        $key = $request->validate(['provider' => ['required', Rule::in($this->providers->keys())]])['provider'];

        $before = $this->providers->activeKey();
        $this->providers->switchTo($key, $request->user());
        $label = $this->providers->label($key);

        if ($before === $key) {
            return back()->with('info', "{$label} is already the active WhatsApp provider.");
        }

        $provider = $this->providers->provider($key);
        $note = $provider->canSend() ? '' : ' Note: '.$provider->unavailableReason();

        return back()->with('success', "New WhatsApp broadcasts now go through {$label}. Broadcasts already sent or queued keep their original provider.{$note}");
    }

    /** Credentials + provider settings. Super admin only (owner decision 2026-11-22). */
    public function updateProvider(Request $request, string $provider): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only the super admin can change messaging credentials.');

        $rules = ['clear' => ['nullable', 'array']];
        foreach ($this->configs->fields($provider) as $field => $def) {
            $rules[$field] = array_filter(['nullable', 'string', 'max:2000', ($def['type'] ?? null) === 'url' ? 'url:https' : null]);
            $rules["clear.{$field}"] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);
        $this->configs->save($provider, $data, $request->user());

        return back()->with('success', $this->providers->label($provider).' settings saved. Empty fields use the server .env values.');
    }

    /** Read-only connectivity check; the result is stored for the status card. */
    public function check(string $provider): JsonResponse
    {
        $result = $this->providers->provider($provider)->checkConnection();
        $this->configs->recordCheck($provider, $result['ok'], $result['message']);

        return response()->json($result + ['checked_at' => now()->toIso8601String()]);
    }

    /** Templates of any provider (active or not), in the composer's normalised shape. */
    public function templates(Request $request, string $provider): JsonResponse
    {
        $p = $this->providers->provider($provider);

        if ($p->templateSource() === 'live' && ! $p->isConfigured()) {
            return response()->json(['templates' => [], 'message' => $p->unavailableReason()]);
        }

        try {
            return response()->json(['templates' => $p->templates($request->boolean('refresh')), 'fetched_at' => now()->toIso8601String()]);
        } catch (WhatsAppProviderException $e) {
            return response()->json(['templates' => [], 'message' => 'Could not load templates from '.$p->label().': '.$e->getMessage()], 502);
        }
    }

    public function template(string $provider, string $template): JsonResponse
    {
        $p = $this->providers->provider($provider);

        try {
            $detail = $p->template($template);
        } catch (WhatsAppProviderException $e) {
            return response()->json(['message' => 'Could not load the template from '.$p->label().': '.$e->getMessage()], 502);
        }

        abort_unless($detail, 404, 'Template not found in '.$p->label().'.');

        return response()->json(['template' => $detail]);
    }

    public function syncTemplates(Request $request, string $provider): RedirectResponse
    {
        $p = $this->providers->provider($provider);

        if (! $p->canSyncTemplates()) {
            return back()->with('error', $p->templateSource() === 'live'
                ? $p->label().' templates are always read live; there is nothing to sync.'
                : (string) ($p->unavailableReason() ?? $p->label().' template sync is not available.'));
        }

        try {
            $count = $p->syncTemplates($request->user()->id);
        } catch (WhatsAppProviderException $e) {
            return back()->with('error', 'Template sync failed: '.$e->getMessage());
        }

        return back()->with('success', "Synced {$count} template(s) from {$p->label()}.");
    }

    public function storeTemplate(Request $request, string $provider): RedirectResponse
    {
        $this->ensureRegistry($provider);
        $data = $request->validate($this->registry->rules($provider, $request->input('language')));
        $template = $this->registry->save($provider, $data, $request->user());

        return back()->with('success', "Template “{$template->name}” added to {$this->providers->label($provider)}.");
    }

    public function updateTemplate(Request $request, WhatsAppTemplate $template): RedirectResponse
    {
        $data = $request->validate($this->registry->rules($template->provider, $request->input('language'), $template));
        $this->registry->save($template->provider, $data, $request->user(), $template);

        return back()->with('success', "Template “{$template->name}” updated.");
    }

    public function destroyTemplate(WhatsAppTemplate $template): RedirectResponse
    {
        $name = $template->name;
        $template->delete(); // broadcasts keep their own snapshot of the template

        return back()->with('success', "Template “{$name}” deleted. Broadcasts already sent with it are unaffected.");
    }

    /** One real template message to a number the admin types, through any provider. */
    public function test(Request $request, string $provider): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'string', 'max:100'],
            'to' => ['required', 'string', 'min:6', 'max:30'],
            'variables' => ['nullable', 'array', 'max:30'],
            'variables.*' => ['nullable', 'string', 'max:500'],
        ]);

        $p = $this->providers->provider($provider);
        if (! $p->canSend()) {
            return response()->json(['ok' => false, 'message' => $p->unavailableReason()], 422);
        }

        try {
            $to = $this->phones->normalize($data['to']);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['to' => 'Enter a valid mobile number with country code, e.g. +91 98765 43210.']);
        }

        try {
            $template = $p->template($data['template_id']);
        } catch (WhatsAppProviderException $e) {
            return response()->json(['ok' => false, 'message' => 'Could not load the template: '.$e->getMessage()], 422);
        }

        if (! $template || ! $template['usable']) {
            throw ValidationException::withMessages(['template_id' => $template['unusable_reason'] ?? 'Pick an approved template.']);
        }

        $variables = [];
        $errors = [];
        foreach ($template['variables'] as $variable) {
            // Same one-line rule as broadcasts (WhatsApp rejects newlines in parameters).
            $value = trim((string) preg_replace('/\s+/u', ' ', (string) ($data['variables'][$variable['key']] ?? '')));
            if ($value === '' && $variable['required']) {
                $errors["variables.{$variable['key']}"] = 'Enter a value for {{'.$variable['key'].'}}.';
            }
            $variables[$variable['key']] = $value;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        try {
            $message = $p->send($to, $template, $variables);
        } catch (WhatsAppProviderException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => $p->label().' accepted the test message to '.$this->phones->mask($to).' (status: '.($message['status'] ?? 'accepted').').',
            'message_id' => $message['id'] ?? null,
        ]);
    }

    private function ensureRegistry(string $provider): void
    {
        abort_unless($this->providers->provider($provider)->templateSource() === 'registry', 422, $this->providers->label($provider).' templates are read live and can’t be edited here.');
    }

    /** Provider-wise WhatsApp usage (all time + last 30 days), from the delivery rows. */
    private function usage(): array
    {
        $provider = DB::raw("coalesce(provider, 'bird')");

        $broadcasts = NotificationLog::where('channel', 'whatsapp')
            ->selectRaw("coalesce(provider, 'bird') as p, count(*) as c, max(created_at) as last_at")
            ->groupBy($provider)->get()->keyBy('p');

        $deliveries = NotificationDelivery::where('channel', 'whatsapp')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("coalesce(provider, 'bird') as p, status, count(*) as c")
            ->groupBy($provider, 'status')->get()->groupBy('p');

        return collect($this->providers->keys())->mapWithKeys(function (string $key) use ($broadcasts, $deliveries) {
            $counts = collect($deliveries->get($key, []))->pluck('c', 'status')->map(fn ($c) => (int) $c);
            $sum = fn (string ...$s) => collect($s)->sum(fn ($k) => $counts[$k] ?? 0);

            return [$key => [
                'broadcasts' => (int) ($broadcasts[$key]->c ?? 0),
                'last_broadcast_at' => isset($broadcasts[$key]->last_at) ? Carbon::parse($broadcasts[$key]->last_at, 'UTC')->toIso8601String() : null,
                'sent_30d' => $sum(...NotificationDelivery::SUCCESS),
                'delivered_30d' => $sum('delivered', 'read'),
                'read_30d' => $sum('read'),
                'failed_30d' => $sum('failed'),
                'skipped_30d' => $sum('skipped'),
                'pending_30d' => $sum('pending', 'sending'),
            ]];
        })->all();
    }

    private function emptyUsage(): array
    {
        return ['broadcasts' => 0, 'last_broadcast_at' => null, 'sent_30d' => 0, 'delivered_30d' => 0, 'read_30d' => 0, 'failed_30d' => 0, 'skipped_30d' => 0, 'pending_30d' => 0];
    }
}
