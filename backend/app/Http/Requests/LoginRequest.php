<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Укажите email.',
            'email.email' => 'Укажите корректный email.',
            'password.required' => 'Укажите пароль.',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function email(): string
    {
        $email = $this->validated('email');

        if (! is_string($email)) {
            return '';
        }

        return $email;
    }

    public function password(): string
    {
        $password = $this->validated('password');

        if (! is_string($password)) {
            return '';
        }

        return $password;
    }
}
