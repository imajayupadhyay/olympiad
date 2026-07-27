<?php

namespace App\Rules;

use App\Services\PhoneNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! filled($value)) {
            return;
        }

        try {
            app(PhoneNumberService::class)->normalize((string) $value);
        } catch (\InvalidArgumentException) {
            $fail('Enter a valid mobile number with its country code.');
        }
    }
}
