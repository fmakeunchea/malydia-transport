<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    $private = dirname(__DIR__, 2) . '/malydia-private';
    if (!is_file($private . '/config.php') || !is_file($private . '/booking.php') || !is_file($private . '/vendor/autoload.php')) {
        http_response_code(503);
        echo '{"ok":false}';
        exit;
    }
    require $private . '/booking.php';
    require $private . '/vendor/autoload.php';
    $config = require $private . '/config.php';
    // Read at most one byte over the limit, rather than loading an unbounded request.
    $body = file_get_contents('php://input', false, null, 0, 6001);
    $status = handleBooking($_SERVER, $body === false ? '' : $body, $config, $private . '/booking-state.json', function (array $data) use ($config): void {
        sendBookingEmail($config, $data);
    });
    http_response_code($status);
    if ($status === 429) header('Retry-After: 60');
    echo json_encode(['ok' => $status === 200]);
} catch (Throwable $error) {
    // Do not expose SMTP credentials or rider details in responses or application logs.
    http_response_code(503);
    echo '{"ok":false}';
}
