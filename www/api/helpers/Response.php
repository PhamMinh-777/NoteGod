<?php
/**
 * API Response Helper
 * Conforms to APISpecifications.docx deterministic JSON envelope
 */

class Response {
    public static function json($data = null, int $statusCode = 200, string $message = 'Success'): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success'   => $statusCode >= 200 && $statusCode < 300,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $statusCode = 400, $details = null): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success'   => false,
            'error'     => [
                'code'    => $statusCode,
                'message' => $message,
                'details' => $details
            ],
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
