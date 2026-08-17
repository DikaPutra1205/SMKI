<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'Berhasil', int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function created(mixed $data = null, string $message = 'Data berhasil dibuat'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message = 'Terjadi kesalahan', int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function notFound(string $message = 'Data tidak ditemukan'): JsonResponse
    {
        return $this->error($message, 404);
    }
}
