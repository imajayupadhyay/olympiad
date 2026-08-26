<?php

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiptIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::in(array_keys(Payment::SOURCES))],
            'method' => ['nullable', 'string', 'max:60'],
            'exam_id' => ['nullable', 'integer', Rule::exists('exams', 'id')],
            'receipt_status' => ['nullable', Rule::in(['issued', 'unissued'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $filters = collect($this->validated())
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();

        $filters['per_page'] ??= 25;

        return $filters;
    }
}
