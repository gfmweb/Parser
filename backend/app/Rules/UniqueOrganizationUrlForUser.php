<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Organization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class UniqueOrganizationUrlForUser implements ValidationRule
{
    public function __construct(private readonly int $userId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $exists = Organization::query()
            ->where('user_id', $this->userId)
            ->where('yandex_url', $value)
            ->exists();

        if ($exists) {
            $fail('The organization URL has already been registered.');
        }
    }
}
