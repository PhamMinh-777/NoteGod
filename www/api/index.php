<?php
/**
 * RESTful API Gateway & Front Controller
 * FinalNote Project
 */

require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Auth.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/NoteController.php';

// Enable CORS if requested
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Parse request URI
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
// Normalize trailing slash
$uri = rtrim($uri, '/');
if (empty($uri)) $uri = '/';

// ---------------------------------------------------------------------
// API ROUTING TABLE
// ---------------------------------------------------------------------

// System health check
if ($uri === '/api' || $uri === '/api/health') {
    Response::json([
        'app'     => 'FinalNote API',
        'version' => '1.0.0',
        'status'  => 'healthy',
        'db'      => 'connected'
    ]);
}

// ---------------------------------------------------------------------
// Auth Routes
// ---------------------------------------------------------------------
if ($uri === '/api/auth/register' && $method === 'POST') {
    AuthController::register();
}
if ($uri === '/api/auth/login' && $method === 'POST') {
    AuthController::login();
}
if ($uri === '/api/auth/logout' && $method === 'POST') {
    AuthController::logout();
}
if ($uri === '/api/auth/me' && $method === 'GET') {
    AuthController::me();
}
if ($uri === '/api/auth/verify' && $method === 'POST') {
    AuthController::verifyEmail();
}
if ($uri === '/api/auth/resend-verification' && $method === 'POST') {
    AuthController::resendVerification();
}
if ($uri === '/api/auth/forgot-password' && $method === 'POST') {
    AuthController::forgotPassword();
}
if ($uri === '/api/auth/reset-password' && $method === 'POST') {
    AuthController::resetPassword();
}

// ---------------------------------------------------------------------
// User & Profile Routes
// ---------------------------------------------------------------------
if ($uri === '/api/user/profile' && $method === 'GET') {
    UserController::getProfile();
}
if ($uri === '/api/user/profile' && ($method === 'PATCH' || $method === 'POST')) {
    UserController::updateProfile();
}
if ($uri === '/api/user/avatar' && $method === 'DELETE') {
    UserController::removeAvatar();
}
if ($uri === '/api/user/change-password' && $method === 'POST') {
    UserController::changePassword();
}
if ($uri === '/api/user/preferences' && ($method === 'PUT' || $method === 'POST')) {
    UserController::updatePreferences();
}
if ($uri === '/api/notes' && $method === 'GET') {
    NoteController::getAll();
}

// ---------------------------------------------------------------------
// Fallback for unhandled endpoints
// ---------------------------------------------------------------------
Response::error("API endpoint not found: [{$method}] {$uri}", 404);
