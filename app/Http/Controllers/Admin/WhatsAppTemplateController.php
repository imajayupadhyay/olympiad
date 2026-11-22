<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Notifications\WhatsApp\WhatsAppProviderException;
use App\Services\Notifications\WhatsApp\WhatsAppProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WhatsApp templates for the composer, from the ACTIVE provider (workflow.md §13.6):
 * Bird's are fetched live (cached briefly), MSG91's come from the local registry.
 */
class WhatsAppTemplateController extends Controller
{
    public function __construct(private readonly WhatsAppProviderManager $providers) {}

    public function index(Request $request): JsonResponse
    {
        $provider = $this->providers->active();
        $meta = ['provider' => ['key' => $provider->key(), 'label' => $provider->label()], 'template_source' => $provider->templateSource()];

        if ($provider->templateSource() === 'live' && ! $provider->isConfigured()) {
            return response()->json($meta + ['configured' => false, 'templates' => [], 'message' => $provider->unavailableReason()], 200);
        }

        try {
            return response()->json($meta + [
                'configured' => $provider->isConfigured(),
                'can_send' => $provider->canSend(),
                'templates' => $provider->templates($request->boolean('refresh')),
                'fetched_at' => now()->toIso8601String(),
                'message' => $provider->canSend() ? null : $provider->unavailableReason(),
            ]);
        } catch (WhatsAppProviderException $e) {
            return response()->json($meta + ['configured' => true, 'templates' => [], 'message' => 'Could not load templates from '.$provider->label().': '.$e->getMessage()], 502);
        }
    }

    public function show(Request $request, string $template): JsonResponse
    {
        $provider = $this->providers->active();
        abort_if($provider->templateSource() === 'live' && ! $provider->isConfigured(), 422, $provider->label().' is not configured.');

        try {
            $detail = $provider->template($template, $request->boolean('refresh'));
        } catch (WhatsAppProviderException $e) {
            return response()->json(['message' => 'Could not load the template from '.$provider->label().': '.$e->getMessage()], 502);
        }

        abort_unless($detail, 404, 'Template not found in '.$provider->label().'.');

        return response()->json(['template' => $detail, 'provider' => $provider->key()]);
    }
}
