<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SchoolDataEntryBulkUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('rows', []))
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                $clean = fn (mixed $value): ?string => $this->cleanString($value);

                $normalized = [
                    'id' => $row['id'] ?? null,
                    'name' => $clean($row['name'] ?? null),
                    'address' => $clean($row['address'] ?? null),
                    'state' => $clean($row['state'] ?? null),
                    'district' => $clean($row['district'] ?? null),
                    'city' => $clean($row['city'] ?? null),
                    'pin_code' => $clean($row['pin_code'] ?? null),
                    'email' => filled($row['email'] ?? null) ? Str::lower($clean($row['email'])) : null,
                    'mobile' => $clean($row['mobile'] ?? null),
                    'head_phone' => $clean($row['head_phone'] ?? null),
                    'is_active' => Arr::exists($row, 'is_active') ? filter_var($row['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) : true,
                ];

                $normalized['coordinators'] = collect($row['coordinators'] ?? [])
                    ->filter(fn ($coordinator) => is_array($coordinator))
                    ->map(fn (array $coordinator): array => [
                        'name' => $clean($coordinator['name'] ?? null),
                        'designation' => $clean($coordinator['designation'] ?? null),
                        'phone' => $clean($coordinator['phone'] ?? null),
                        'email' => filled($coordinator['email'] ?? null) ? Str::lower($clean($coordinator['email'])) : null,
                    ])
                    ->filter(fn (array $coordinator): bool => collect($coordinator)->contains(fn ($value) => filled($value)))
                    ->values()
                    ->all();

                return $normalized;
            })
            ->values()
            ->all();

        $this->merge(['rows' => $rows]);
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.id' => ['required', 'integer', 'exists:schools,id'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.address' => ['nullable', 'string', 'max:500'],
            'rows.*.state' => ['required', 'string', 'max:100'],
            'rows.*.district' => ['nullable', 'string', 'max:100'],
            'rows.*.city' => ['nullable', 'string', 'max:100'],
            'rows.*.pin_code' => ['nullable', 'string', 'regex:/\A\d{6}\z/'],
            'rows.*.email' => ['nullable', 'email', 'max:255'],
            'rows.*.mobile' => ['nullable', 'string', 'max:25', 'regex:/\A[0-9+\-\s().]{6,25}\z/'],
            'rows.*.head_phone' => ['nullable', 'string', 'max:25', 'regex:/\A[0-9+\-\s().]{6,25}\z/'],
            'rows.*.is_active' => ['required', 'boolean'],
            'rows.*.coordinators' => ['nullable', 'array', 'max:5'],
            'rows.*.coordinators.*.name' => ['required', 'string', 'max:150'],
            'rows.*.coordinators.*.designation' => ['nullable', 'string', 'max:120'],
            'rows.*.coordinators.*.phone' => ['nullable', 'string', 'max:25', 'regex:/\A[0-9+\-\s().]{6,25}\z/'],
            'rows.*.coordinators.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.max' => 'Save up to 200 changed schools at a time.',
            'rows.*.pin_code.regex' => 'Enter a valid 6 digit PIN code.',
            '*.regex' => 'Use a valid phone number format.',
        ];
    }

    public function rows(): array
    {
        return $this->validated('rows');
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        return preg_replace('/\s+/', ' ', $value);
    }
}
