<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseApiController extends Controller
{
    protected function success(string $message = 'Success', mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'error' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function error(string $message = 'Error', mixed $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'error' => true,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'error' => false,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'has_more' => $paginator->hasMorePages(),
                'total' => $paginator->total(),
            ],
        ], $status);
    }

    protected function ok(string $message = 'Success', mixed $data = null): JsonResponse
    {
        return $this->success($message, $data, 200);
    }

    protected function created(string $message = 'Resource created', mixed $data = null): JsonResponse
    {
        return $this->success($message, $data, 201);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, null, 404);
    }

    protected function unprocessableEntity(string $message = 'Unprocessable Entity', mixed $errors = null): JsonResponse
    {
        return $this->error($message, $errors, 422);
    }
}
