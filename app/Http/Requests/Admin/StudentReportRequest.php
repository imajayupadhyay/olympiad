<?php

namespace App\Http\Requests\Admin;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StudentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'class_level_id' => ['nullable', 'integer', Rule::exists('class_levels', 'id')],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'exam_id' => ['nullable', 'integer', Rule::exists('exams', 'id')],
            'state' => ['nullable', 'string', Rule::in(User::indianStates())],
            'account_status' => ['nullable', Rule::in(['active', 'inactive'])],
            'enrollment_status' => ['nullable', Rule::in(['enrolled', 'not_enrolled'])],
            'payment_status' => ['nullable', Rule::in(['paid', 'unpaid', 'pending', 'failed', 'refunded', 'no_payments'])],
            'registered_from' => ['nullable', 'date_format:Y-m-d'],
            'registered_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:registered_from'],
            'paid_from' => ['nullable', 'date_format:Y-m-d'],
            'paid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:paid_from'],
            'sort' => ['nullable', Rule::in(['name', 'registered_at', 'paid_total', 'enrollments'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('exam_id') || ! $this->filled('subject_id')) {
                    return;
                }

                $matches = Exam::query()
                    ->whereKey($this->integer('exam_id'))
                    ->where('subject_id', $this->integer('subject_id'))
                    ->exists();

                if (! $matches) {
                    $validator->errors()->add('exam_id', 'The selected olympiad does not belong to the selected subject.');
                }
            },
        ];
    }

    public function filters(): array
    {
        $filters = collect($this->validated())
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();

        $filters['sort'] ??= 'registered_at';
        $filters['direction'] ??= 'desc';
        $filters['per_page'] ??= 25;

        return $filters;
    }
}
