<?php

namespace App\Http\Requests\Admin;

use App\Models\ReceiptSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiptSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:150'],
            'gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9A-Z]{15}$/i'],
            'address' => ['nullable', 'string', 'max:1000'],
            'state' => ['nullable', 'string', 'max:100'],
            'state_code' => ['nullable', 'string', 'max:2'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:150'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'hsn_sac' => ['nullable', 'string', 'max:20'],
            'service_description' => ['required', 'string', 'max:255'],
            'gst_rate' => ['required', 'numeric', 'min:0', 'max:28'],
            'prices_include_gst' => ['nullable', 'boolean'],
            'receipt_prefix' => ['required', 'string', 'max:60'],
            'receipt_padding' => ['required', 'integer', 'min:1', 'max:10'],
            'financial_year_start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'next_sequence_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'visible_fields' => ['nullable', 'array'],
            'visible_fields.*' => ['string', Rule::in(array_keys(ReceiptSetting::VISIBLE_FIELD_LABELS))],
            'footer_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'prices_include_gst' => $this->boolean('prices_include_gst'),
            'remove_logo' => $this->boolean('remove_logo'),
            'visible_fields' => $this->input('visible_fields', []),
        ]);
    }

    public function settingsData(): array
    {
        $data = $this->validated();
        $data['gstin'] = filled($data['gstin'] ?? null) ? strtoupper((string) $data['gstin']) : null;
        $data['state_code'] = filled($data['state_code'] ?? null) ? str_pad((string) $data['state_code'], 2, '0', STR_PAD_LEFT) : null;

        return collect($data)
            ->except(['logo', 'remove_logo', 'next_sequence_number'])
            ->all();
    }
}
