<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    public function index(): Response
    {
        HomepageSection::ensureDefaults();

        $sections = HomepageSection::with('updater:id,name')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (HomepageSection $section) => [
                'id' => $section->id,
                'key' => $section->key,
                'title' => $section->title,
                'is_enabled' => $section->is_enabled,
                'sort_order' => $section->sort_order,
                'content' => $section->content,
                'updated_at' => optional($section->updated_at)->toIso8601String(),
                'updater' => $section->updater ? [
                    'id' => $section->updater->id,
                    'name' => $section->updater->name,
                ] : null,
            ]);

        return Inertia::render('Admin/Content/Index', [
            'sections' => $sections,
            'stats' => [
                'total' => $sections->count(),
                'enabled' => $sections->where('is_enabled', true)->count(),
                'disabled' => $sections->where('is_enabled', false)->count(),
                'last_updated' => HomepageSection::latest('updated_at')->value('updated_at')?->toIso8601String(),
            ],
            'defaults' => collect(HomepageSection::defaults())->mapWithKeys(fn ($section) => [
                $section['key'] => $section['content'],
            ]),
        ]);
    }

    public function update(Request $request, HomepageSection $section): RedirectResponse
    {
        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'content' => ['required', 'array'],
        ]);

        $section->update([
            'is_enabled' => $data['is_enabled'],
            'content' => HomepageSection::mergeWithDefault($section->key, $data['content']),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', "{$section->title} updated.");
    }

    public function reset(Request $request, HomepageSection $section): RedirectResponse
    {
        $default = HomepageSection::defaultFor($section->key);

        abort_if(! $default, 404);

        $section->update([
            'is_enabled' => true,
            'content' => $default['content'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', "{$section->title} restored to the default content.");
    }
}
