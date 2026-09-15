<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexReviewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('rating') === '') {
            $this->merge(['rating' => null]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'rating' => ['sometimes', 'nullable', 'integer', 'in:1,2,3,4,5'],
        ];
    }

    public function page(): int
    {
        $page = $this->validated('page', 1);

        if (! is_numeric($page)) {
            return 1;
        }

        return max(1, (int) $page);
    }

    public function rating(): ?int
    {
        if (! $this->exists('rating') || $this->input('rating') === null || $this->input('rating') === '') {
            return null;
        }

        $rating = $this->validated('rating');

        if (! is_numeric($rating)) {
            return null;
        }

        return (int) $rating;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page.integer' => 'Номер страницы должен быть числом.',
            'page.min' => 'Номер страницы должен быть не меньше 1.',
            'rating.integer' => 'Оценка должна быть числом от 1 до 5.',
            'rating.in' => 'Оценка должна быть числом от 1 до 5.',
        ];
    }
}
