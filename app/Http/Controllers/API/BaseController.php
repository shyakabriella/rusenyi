<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    /**
     * Return a successful API response.
     */
    public function sendResponse(
        mixed $result,
        string $message = 'Request completed successfully.',
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result,
        ], $code);
    }

    /**
     * Return an error API response.
     */
    public function sendError(
        string $message,
        mixed $errors = null,
        int $code = 400
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
