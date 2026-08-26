<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceiptSettingRequest;
use App\Models\ReceiptSetting;
use App\Services\ReceiptNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptSettingController extends Controller
{
    public function index(ReceiptNumberService $numbers): Response
    {
        $settings = ReceiptSetting::current();

        return Inertia::render('Admin/Settings/Receipts/Index', [
            'settings' => array_merge($settings->toArray(), [
                'logo_url' => $settings->logoUrl(),
            ]),
            'visibleFields' => ReceiptSetting::VISIBLE_FIELD_LABELS,
            'sequence' => $numbers->preview(),
        ]);
    }

    public function update(ReceiptSettingRequest $request, ReceiptNumberService $numbers)
    {
        $settings = ReceiptSetting::current();
        $oldLogo = $settings->logo_path;
        $newLogo = $request->hasFile('logo')
            ? $request->file('logo')->store('receipt-logos', 'public')
            : null;

        DB::transaction(function () use ($request, $numbers, $settings, $newLogo): void {
            $data = $request->settingsData();

            if ($request->boolean('remove_logo')) {
                $data['logo_path'] = null;
            }

            if ($newLogo) {
                $data['logo_path'] = $newLogo;
            }

            $settings->update($data);
            $numbers->setNextNumber((int) $request->validated('next_sequence_number'));
        });

        if ($oldLogo && ($request->boolean('remove_logo') || $newLogo)) {
            Storage::disk('public')->delete($oldLogo);
        }

        return back()->with('success', 'Receipt settings saved.');
    }
}
