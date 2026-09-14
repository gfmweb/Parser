<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\UniqueOrganizationUrlForUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return [
            'url' => [
                'required',
                'string',
                'max:2048',
                'regex:/yandex\.(ru|com)\/maps/',
                new UniqueOrganizationUrlForUser($user->id),
            ],
        ];
    }

    public function organizationUrl(): string
    {
        $url = $this->validated('url');

        if (! is_string($url)) {
            return '';
        }

        return $url;
    }
}
