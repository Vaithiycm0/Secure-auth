<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

/**
 * Send JSON response and stop execution.
 */
function response(bool $success, string $message, int $statusCode = 200): never
{
    http_response_code($statusCode);

    echo json_encode(
        [
            'success' => $success,
            'message' => $message
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

/**
 * Only POST requests are allowed.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Invalid request method.', 405);
}

/**
 * Get login credentials.
 */
$usernameOrEmail = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

/**
 * Basic validation.
 */
if ($usernameOrEmail === '' || $password === '') {
    response(
        false,
        'Username/email and password are required.',
        422
    );
}

try {

    /*
    |--------------------------------------------------------------------------
    | Find User
    |--------------------------------------------------------------------------
    | We use two different placeholders because PDO MySQL prepared
    | statements should not reuse the same named parameter.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'SELECT id, username, email, password_hash
         FROM users
         WHERE username = :username
            OR email = :email
         LIMIT 1'
    );

    $stmt->execute([
        ':username' => $usernameOrEmail,
        ':email' => $usernameOrEmail
    ]);

    $user = $stmt->fetch();

    /*
    |--------------------------------------------------------------------------
    | Verify Password
    |--------------------------------------------------------------------------
    */

    if (
        !$user ||
        !password_verify($password, (string) $user['password_hash'])
    ) {
        response(
            false,
            'Invalid username/email or password.',
            401
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Secure Session ID
    |--------------------------------------------------------------------------
    */

    $sessionId = bin2hex(random_bytes(32));

    $sessionTtl = (int) env('SESSION_TTL', '3600');

    if ($sessionTtl <= 0) {
        $sessionTtl = 3600;
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Session Data
    |--------------------------------------------------------------------------
    */

    $sessionData = [
        'user_id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'email' => (string) $user['email'],
        'created_at' => time()
    ];

    /*
    |--------------------------------------------------------------------------
    | Store Session in Redis
    |--------------------------------------------------------------------------
    */

    $sessionKey = 'session:' . $sessionId;

    $sessionJson = json_encode(
        $sessionData,
        JSON_THROW_ON_ERROR
    );

    $redis->setex(
        $sessionKey,
        $sessionTtl,
        $sessionJson
    );

    /*
    |--------------------------------------------------------------------------
    | Set HTTP-Only Session Cookie
    |--------------------------------------------------------------------------
    |
    | secure = false is required for local HTTP development.
    | It should be changed to true when deployed with HTTPS.
    |--------------------------------------------------------------------------
    */

    $isHttps = (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    );

    setcookie(
        'session_id',
        $sessionId,
        [
            'expires' => time() + $sessionTtl,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $isHttps
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Login Successful
    |--------------------------------------------------------------------------
    */

    response(
        true,
        'Login successful. Redirecting...'
    );

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Log Technical Error
    |--------------------------------------------------------------------------
    | Do not expose database/Redis errors to the user.
    |--------------------------------------------------------------------------
    */

    error_log(
        'Login error: ' . $e->getMessage()
    );

    response(
        false,
        'Login could not be completed.',
        500
    );
}