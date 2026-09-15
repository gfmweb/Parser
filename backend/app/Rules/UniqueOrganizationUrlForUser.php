<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Organization;
use App\Services\Parser\YandexUrlParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class UniqueOrganizationUrlForUser implements ValidationRule
{
    public function __construct(
        private readonly int $userId,
        private readonly YandexUrlParser $urlParser = new YandexUrlParser,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $canonical = $this->urlParser->canonicalize($value);
        $incomingOrgId = $this->extractOrgIdOrNull($value);

        $exists = Organization::query()
            ->where('user_id', $this->userId)
            ->where(function (Builder $query) use ($canonical, $incomingOrgId): void {
                $query->where('yandex_url', $canonical);

                if ($incomingOrgId === null) {
                    return;
                }

                $query->orWhere('yandex_id', $incomingOrgId)
                    ->orWhere('yandex_url', 'like', '%/org/'.$incomingOrgId)
                    ->orWhere('yandex_url', 'like', '%/org/'.$incomingOrgId.'/%')
                    ->orWhere('yandex_url', 'like', '%/org/%/'.$incomingOrgId)
                    ->orWhere('yandex_url', 'like', '%/org/%/'.$incomingOrgId.'/%');
            })
            ->exists();

        if ($exists) {
            $fail('Эта организация уже добавлена.');
        }
    }

    private function extractOrgIdOrNull(string $url): ?string
    {
        try {
            return $this->urlParser->extractOrgId($url);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
