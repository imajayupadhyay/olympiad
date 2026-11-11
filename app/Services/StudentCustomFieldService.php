<?php

namespace App\Services;

use App\Models\StudentCustomField;
use App\Models\StudentCustomFieldValue;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin-defined student details (§12.15): definitions, per-student values, profile
 * validation and their share of profile completion. One owner for every rule.
 */
class StudentCustomFieldService
{
    public const MAX_FIELDS = 30;

    /** Built-in keys a custom field may never shadow. */
    private const RESERVED = ['name', 'email', 'phone', 'dob', 'class', 'school', 'photo', 'roll_number', 'registration_no'];

    /** @return Collection<int, StudentCustomField> */
    public function active(): Collection
    {
        return StudentCustomField::active()->ordered()->get();
    }

    /** @return array<string, string> custom key => stored value */
    public function valuesFor(User $user): array
    {
        if (! $user->exists) {
            return [];
        }

        return StudentCustomFieldValue::query()
            ->join('student_custom_fields', 'student_custom_fields.id', '=', 'student_custom_field_values.student_custom_field_id')
            ->where('student_custom_field_values.user_id', $user->id)
            ->pluck('student_custom_field_values.value', 'student_custom_fields.key')
            ->filter(fn ($v) => filled($v))
            ->all();
    }

    /** Profile form payload: active fields with the student's current values. */
    public function forProfile(User $user): array
    {
        $values = $this->valuesFor($user);

        return $this->active()->map(fn (StudentCustomField $f) => [
            'key' => $f->key,
            'label' => $f->label,
            'type' => $f->type,
            'options' => $f->options ?? [],
            'placeholder' => $f->placeholder,
            'help_text' => $f->help_text,
            'required' => $f->is_required,
            'value' => $values[$f->key] ?? '',
        ])->values()->all();
    }

    /**
     * Required active fields for completion/eligibility, keyed custom:{key}.
     *
     * @return array<string, array{label:string, filled:bool}>
     */
    public function requiredCompletion(User $user): array
    {
        $required = StudentCustomField::active()->where('is_required', true)->ordered()->get();

        if ($required->isEmpty()) {
            return [];
        }

        $values = $this->valuesFor($user);

        return $required->mapWithKeys(fn (StudentCustomField $f) => [
            $f->ticketKey() => ['label' => $f->label, 'filled' => filled($values[$f->key] ?? null)],
        ])->all();
    }

    /** Validation rules for the profile form's `custom` object. Required-ness is enforced at submit, not save. */
    public function rules(): array
    {
        $rules = ['custom' => ['nullable', 'array']];

        foreach ($this->active() as $field) {
            $rules["custom.{$field->key}"] = match ($field->type) {
                'date' => ['nullable', 'date'],
                'select' => ['nullable', 'string', Rule::in($field->options ?? [])],
                default => ['nullable', 'string', 'max:150'],
            };
        }

        return $rules;
    }

    /** @return array<string, string> attribute names for validation messages */
    public function attributes(): array
    {
        return $this->active()->mapWithKeys(fn (StudentCustomField $f) => ["custom.{$f->key}" => $f->label])->all();
    }

    /** Upsert the submitted values for active fields; blank clears. Unknown keys are ignored. */
    public function save(User $user, array $input): void
    {
        $fields = $this->active()->keyBy('key');

        DB::transaction(function () use ($user, $input, $fields) {
            foreach ($input as $key => $value) {
                $field = $fields->get($key);
                if (! $field) {
                    continue;
                }

                $value = is_string($value) ? trim($value) : $value;

                if (blank($value)) {
                    StudentCustomFieldValue::where('student_custom_field_id', $field->id)->where('user_id', $user->id)->delete();

                    continue;
                }

                StudentCustomFieldValue::updateOrCreate(
                    ['student_custom_field_id' => $field->id, 'user_id' => $user->id],
                    ['value' => $field->type === 'date' ? date('Y-m-d', strtotime((string) $value)) : Str::limit((string) $value, 250, '')],
                );
            }
        });
    }

    /** Human display value (dates as "14 March 2015"). */
    public function display(StudentCustomField $field, ?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return $field->type === 'date' && strtotime($value) ? date('j F Y', strtotime($value)) : $value;
    }

    /* ───────────────────────── admin CRUD ───────────────────────── */

    public function definitionRules(?StudentCustomField $field = null): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'type' => [$field ? 'sometimes' : 'required', Rule::in(array_keys(StudentCustomField::TYPES))],
            'options' => ['nullable', 'array', 'max:30', 'required_if:type,select'],
            'options.*' => ['string', 'max:60', 'distinct'],
            'placeholder' => ['nullable', 'string', 'max:120'],
            'help_text' => ['nullable', 'string', 'max:200'],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function create(array $data): StudentCustomField
    {
        return StudentCustomField::create([
            ...$this->clean($data),
            'key' => $this->uniqueKey($data['label']),
            'sort_order' => (int) StudentCustomField::max('sort_order') + 1,
        ]);
    }

    /** The key and type are immutable once values may exist (changing them would orphan data). */
    public function update(StudentCustomField $field, array $data): StudentCustomField
    {
        $clean = $this->clean([...$data, 'type' => $field->type]);
        unset($clean['type']);
        $field->update($clean);

        return $field;
    }

    public function delete(StudentCustomField $field): void
    {
        // Values cascade; the ticket config drops the unknown key on the next render/save.
        $field->delete();
    }

    /** @param  list<int>  $ids */
    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                StudentCustomField::whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });
    }

    private function clean(array $data): array
    {
        $type = $data['type'] ?? 'text';
        $options = $type === 'select'
            ? collect($data['options'] ?? [])->map(fn ($o) => trim((string) $o))->filter()->unique()->values()->all()
            : null;

        return [
            'label' => trim($data['label']),
            'type' => $type,
            'options' => $options,
            'placeholder' => filled($data['placeholder'] ?? null) ? trim($data['placeholder']) : null,
            'help_text' => filled($data['help_text'] ?? null) ? trim($data['help_text']) : null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function uniqueKey(string $label): string
    {
        $base = Str::of($label)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(40, '')->value() ?: 'field';
        if (in_array($base, self::RESERVED, true)) {
            $base .= '_custom';
        }

        $key = $base;
        $i = 2;
        while (StudentCustomField::where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }

        return $key;
    }
}
