<?php

namespace App\Http\Requests\Admin;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'school_code', 'category', 'name', 'address', 'state', 'district', 'city',
            'pin_code', 'email', 'mobile', 'head_phone',
        ];

        $normalized = [];
        foreach ($fields as $field) {
            $normalized[$field] = $this->cleanString($this->input($field));
        }

        if (filled($normalized['school_code'])) {
            $normalized['school_code'] = Str::upper($normalized['school_code']);
        }

        if (filled($normalized['category'])) {
            $normalized['category'] = Str::upper($normalized['category']);
        }

        if (filled($normalized['email'])) {
            $normalized['email'] = Str::lower($normalized['email']);
        }

        $coordinators = collect($this->input('coordinators', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn ($row) => [
                'name' => $this->cleanString($row['name'] ?? null),
                'email' => filled($row['email'] ?? null) ? Str::lower($this->cleanString($row['email'])) : null,
                'phone' => $this->cleanString($row['phone'] ?? null),
                'designation' => $this->cleanString($row['designation'] ?? null),
            ])
            ->filter(fn ($row) => collect($row)->contains(fn ($value) => filled($value)))
            ->values()
            ->all();

        $this->merge(array_merge($normalized, ['coordinators' => $coordinators]));
    }

    public function rules(): array
    {
        $school = $this->route('school');
        $schoolId = $school instanceof School ? $school->id : null;

        return [
            'school_code' => [
                'required',
                'string',
                'max:50',
                'regex:/\A[A-Za-z0-9][A-Za-z0-9._\/-]*\z/',
                Rule::unique('schools', 'school_code')->ignore($schoolId),
            ],
            'category' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'state' => ['required', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'pin_code' => ['nullable', 'string', 'regex:/\A\d{6}\z/'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:25', 'regex:/\A[0-9+\-\s().]{6,25}\z/'],
            'head_phone' => ['nullable', 'string', 'max:25', 'regex:/\A[0-9+\-\s().]{6,25}\z/'],
            'is_active' => ['sometimes', 'boolean'],
            'source_school_id' => ['nullable', 'integer', Rule::exists('schools', 'id')],
            'coordinators' => ['nullable', 'array', 'max:20'],
            'coordinators.*.name' => ['required', 'string', 'max:150'],
            'coordinators.*.email' => ['nullable', 'email', 'max:255'],
            'coordinators.*.phone' => ['nullable', 'string', 'max:25', 'regex:/\A[0-9+\-\s().]{6,25}\z/'],
            'coordinators.*.designation' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_code.regex' => 'Use letters, numbers, dots, slashes, hyphens or underscores only.',
            'pin_code.regex' => 'Enter a valid 6 digit PIN code.',
            '*.regex' => 'Use a valid phone number format.',
        ];
    }

    public function schoolAttributes(): array
    {
        $attributes = $this->validated();
        unset($attributes['coordinators'], $attributes['source_school_id']);

        $attributes['is_active'] = array_key_exists('is_active', $attributes)
            ? (bool) $attributes['is_active']
            : true;
        $attributes['is_managed'] = true;

        return $attributes;
    }

    public function coordinatorAttributes(): array
    {
        return $this->validated('coordinators', []);
    }

    public function sourceSchoolId(): ?int
    {
        return $this->filled('source_school_id') ? $this->integer('source_school_id') : null;
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
