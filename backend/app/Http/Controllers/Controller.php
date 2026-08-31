<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function success(
        $data = null,
        string $message = 'OK',
        int $status = 200
    ) {
        return response()->json([
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    protected function error(
        string $message,
        int $status = 400,
        $errors = null
    ) {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
