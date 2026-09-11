<?php
declare(strict_types=1);
require __DIR__ . '/../server/booking.php';
function check(bool $result, string $message): void {
    if (!$result) throw new RuntimeException($message);
}
$config = ['origins' => ['https://example.com'], 'smtp_host' => 'smtp.example.com', 'smtp_port' => 465, 'smtp_username' => 'info@malydiahealth.com', 'smtp_password' => 'test-only'];
$request = ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'https://example.com', 'CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1'];
$data = ['firstName' => 'Test', 'lastName' => 'Requester', 'phone' => '5405550100', 'email' => '', 'pickup' => '100 Example St, Spotsylvania, VA', 'dropoff' => '200 Example St, Stafford, VA', 'date' => '2099-01-01', 'time' => '09:00', 'mobility' => 'wheelchair', 'returnRide' => 'yes', 'requestId' => 'a1234567-1234-1234-1234-123456789012', 'website' => ''];
$state = tempnam(__DIR__, 'malydia-test-');
$calls = 0;
$send = function (array $received) use (&$calls, $data): void {
    $calls++;
    foreach (['pickup', 'dropoff', 'phone', 'date', 'time', 'mobility'] as $field) check(str_contains(bookingText($received), $data[$field]), 'Missing trip field');
    check(!isset($received['unexpected']), 'Extra field forwarded');
};
$run = fn($payload, $sender = null, $headers = null, $settings = null) => handleBooking($headers ?? $request, json_encode($payload), $settings ?? $config, $state, $sender ?? $send);
try {
    foreach ([null, [], array_replace($data, ['pickup' => '']), array_replace($data, ['mobility' => 'unknown']), array_replace($data, ['date' => '2020-01-01']), array_replace($data, ['date' => '2099-02-30']), array_replace($data, ['time' => '25:00']), array_replace($data, ['website' => 'spam']), array_replace($data, ['email' => 'bad']), array_replace($data, ['phone' => "5405550100\r\nInjected"])] as $invalid) check($run($invalid) === 400, 'Invalid payload accepted');
    check($run($data, null, array_replace($request, ['HTTP_ORIGIN' => 'https://other.example'])) === 403, 'Cross-origin request accepted');
    check($run($data, null, array_replace($request, ['REQUEST_METHOD' => 'GET'])) === 405, 'GET accepted');
    check($run($data, null, array_replace($request, ['CONTENT_TYPE' => 'text/plain'])) === 415, 'Wrong content type accepted');
    check(handleBooking($request, str_repeat(' ', 6001), $config, $state, $send) === 413, 'Oversize request accepted');
    check($run($data, null, null, array_replace($config, ['smtp_password' => ''])) === 503, 'Unconfigured sender reported success');
    check($calls === 0, 'Invalid request contacted sender');
    $failure = function (): void { throw new RuntimeException('Synthetic SMTP failure'); };
    check($run($data, $failure) === 502, 'Delivery failure reported success');
    check($run(array_replace($data, ['unexpected' => 'not forwarded'])) === 200, 'Valid request rejected');
    check($run($data) === 200 && $calls === 1, 'Retry generated duplicate email');
    $disk = file_get_contents($state);
    check(!str_contains($disk, $data['pickup']) && !str_contains($disk, $data['phone']), 'Rider data written to state');
    // Failed sends count toward rate limits, but can be retried after the window.
    file_put_contents($state, '');
    for ($i = 0; $i < 5; $i++) check($run($data, $failure) === 502, 'Failure did not remain retryable');
    check($run($data) === 429, 'Rate limit not enforced');
    file_put_contents($state, 'not-json');
    check($run($data) === 503, 'Corrupted state failed open');
    echo "PASS: validation, origins, configuration, delivery failure/success, deduplication, private state, and rate limits\n";
} finally { unlink($state); }
