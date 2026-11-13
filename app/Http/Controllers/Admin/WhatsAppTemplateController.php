<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Notifications\BirdException;
use App\Services\Notifications\BirdTemplateCatalogue;
use App\Services\Notifications\BirdWhatsAppGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bird WhatsApp templates for the composer, fetched live (cached briefly). */
class WhatsAppTemplateController extends Controller
{
    public function __construct(
        private readonly BirdTemplateCatalogue $catalogue,
        private readonly BirdWhatsAppGateway $bird,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->bird->isConfigured()) {
            return response()->json(['configured' => false, 'templates' => [], 'message' => 'Bird is not configured. Set BIRD_API_KEY and BIRD_BASE_URL in the server .env.'], 200);
        }

        try {
            return response()->json([
                'configured' => true,
                'templates' => $this->catalogue->list($request->boolean('refresh')),
                'fetched_at' => now()->toIso8601String(),
            ]);
        } catch (BirdException $e) {
            return response()->json(['configured' => true, 'templates' => [], 'message' => 'Could not load templates from Bird: '.$e->getMessage()], 502);
        }
    }

    public function show(Request $request, string $template): JsonResponse
    {
        abort_unless($this->bird->isConfigured(), 422, 'Bird is not configured.');

        try {
            $detail = $this->catalogue->find($template, $request->boolean('refresh'));
        } catch (BirdException $e) {
            return response()->json(['message' => 'Could not load the template from Bird: '.$e->getMessage()], 502);
        }

        abort_unless($detail, 404, 'Template not found in Bird.');

        return response()->json(['template' => $detail]);
    }
}
