<?php

use Illuminate\Http\JsonResponse;

if (! function_exists('successResponse')) {
    /**
     * Returns app standard success response
     *
     * @return array{data: mixed, message: string, error: bool}
     */
    function successResponse(mixed $data = [], string $message = 'Operation Successful!'): array
    {
        return [
            'error' => false,
            'message' => $message,
            'data' => $data,
        ];
    }
}

if (! function_exists('errorResponse')) {
    /**
     * Returns app standard error response
     *
     * @param  array  $data
     * @return array{data: mixed, message: string, error: bool}
     */
    function errorResponse(string $message = 'An Error Occured!', mixed $data = []): array
    {
        return [
            'error' => true,
            'message' => $message,
            'data' => $data,
        ];
    }
}

if (! function_exists('jsonSuccessResponse')) {
    /**
     * Returns app standard success response
     */
    function jsonSuccessResponse(string $message = 'Operation Successful!', array $data = [], int $code = 200): JsonResponse
    {
        return response()->json([
            'error' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}

if (! function_exists('jsonErrorResponse')) {
    /**
     * Returns app standard error response
     */
    function jsonErrorResponse(string $message = 'An Error Occured!', array $data = [], int $code = 500): JsonResponse
    {
        return response()->json([
            'error' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
