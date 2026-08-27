<?php

namespace App\Http\Requests\Admin;

use App\Models\ReceiptSetting;
use App\Support\GstStateCodes;
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
            'state_code' => ['nullable', 'string', 'max:2', 'regex:/^[0-9]{1,2}$/'],
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

    public function messages(): array
    {
        return [
            'state_code.regex' => 'The state code must be the 2-digit GST code, for example 07.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_merge([
            'prices_include_gst' => $this->boolean('prices_include_gst'),
            'remove_logo' => $this->boolean('remove_logo'),
            'visible_fields' => $this->input('visible_fields', []),
        ], $this->normalisedState()));
    }

    /**
     * The State box accepts either a plain name or the printed "Delhi / 07"
     * form. Split it so the stored state stays a clean name and the GST code
     * keeps living in its own column (interstate detection compares names).
     *
     * @return array{state: ?string, state_code: ?string}
     */
    private function normalisedState(): array
    {
        $parsed = GstStateCodes::split($this->input('state'));
        $typed = trim((string) $this->input('state_code'));

        // A code written inside the State box wins, then the code box, then
        // the canonical code looked up from a recognised state name. A typed
        // code that is not numeric is passed through untouched so validation
        // rejects it instead of silently discarding it.
        $code = $parsed['code'];

        if ($code === null && $typed !== '') {
            $code = preg_match('/^[0-9]{1,2}$/', $typed) === 1 ? GstStateCodes::pad($typed) : $typed;
        }

        $code ??= GstStateCodes::codeFor($parsed['name']);

        return [
            'state' => $parsed['name'],
            'state_code' => $code,
        ];
    }

    public function settingsData(): array
    {
        $data = $this->validated();
        $data['gstin'] = filled($data['gstin'] ?? null) ? strtoupper((string) $data['gstin']) : null;
        $data['state_code'] = GstStateCodes::pad($data['state_code'] ?? null);

        return collect($data)
            ->except(['logo', 'remove_logo', 'next_sequence_number'])
            ->all();
    }
}
