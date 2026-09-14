<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use stdClass;

trait ApiResponder
{
    /**
     * @param  array<string, mixed>  $errors
     */
    protected function apiError(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors === [] ? new stdClass : $errors,
        ], $status);
    }

    protected function apiSuccess(mixed $data, int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve();
        }

        return response()->json($data, $status);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{current_page: int, last_page: int, total: int, per_page: int}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ];
    }
}
