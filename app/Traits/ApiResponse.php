<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function success(?array $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    protected function error(string $message, int $status = 422, ?array $errors = null): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], fn (mixed $value): bool => $value !== null), $status);
    }

    protected function systemError(string $message = 'Something went wrong. Please try again.'): JsonResponse
    {
        return $this->error($message, 500);
    }
}
