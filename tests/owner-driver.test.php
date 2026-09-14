<?php
declare(strict_types=1);
require __DIR__ . '/../server/owner-driver.php';
function checkDriver(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$config = ['origins' => ['https://example.com'], 'smtp_host' => 'smtp.example.com', 'smtp_port' => 465, 'smtp_username' => 'info@malydiahealth.com', 'smtp_password' => 'test-only'];
$request = ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'https://example.com', 'CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1'];
$data = ['firstName' => 'Test', 'lastName' => 'Applicant', 'email' => 'test@example.com', 'phone' => '5405550100', 'city' => 'Spotsylvania', 'areas' => ['Stafford', 'Fredericksburg'], 'availability' => 'Weekdays', 'availabilityDetails' => '', 'vehicleAccess' => 'I own a vehicle', 'vehicleMake' => 'Example', 'vehicleModel' => 'Van', 'vehicleYear' => '2020', 'seats' => '4', 'vehicleType' => 'Minivan', 'licenseStatus' => 'Yes', 'insuranceStatus' => 'Personal auto coverage', 'experience' => 'New to passenger transportation', 'consent' => 'yes', 'requestId' => 'a1234567-1234-1234-1234-123456789012'];
$state = tempnam(__DIR__, 'driver-test-');
$calls = 0;
$sender = function ($received) use (&$calls): void {
    $calls++;
    checkDriver(!isset($received['ssn']), 'Extra data forwarded');
    $text = driverText($received);
    foreach (['test@example.com', '5405550100', 'Spotsylvania', 'Fredericksburg', '2020', 'Personal auto coverage', 'consideration: yes'] as $value) checkDriver(str_contains($text, $value), 'Missing application detail');
};
$run = fn($payload, $send = null, $headers = null, $settings = null) => handleDriver($headers ?? $request, json_encode($payload), $settings ?? $config, $state, $send ?? $sender);
try {
    foreach (['consent' => 'no', 'email' => 'invalid', 'firstName' => '', 'phone' => '123', 'areas' => [], 'seats' => '0', 'vehicleYear' => '9999', 'vehicleType' => 'invalid', 'licenseStatus' => 'invalid', 'website' => 'bot', 'availabilityDetails' => "\r\ninjected"] as $key => $value) checkDriver($run(array_replace($data, [$key => $value])) === 400, 'Invalid ' . $key . ' accepted');
    checkDriver($run(array_replace($data, ['areas' => [['nested']]])) === 400, 'Nested area accepted');
    checkDriver($run($data, null, array_replace($request, ['HTTP_ORIGIN' => 'https://evil.example'])) === 403, 'Cross-origin request accepted');
    checkDriver($run($data, null, null, array_replace($config, ['smtp_password' => ''])) === 503, 'Missing configuration accepted');
    checkDriver($calls === 0, 'Invalid request sent');
    $fail = function (): void { throw new RuntimeException('SMTP unavailable'); };
    checkDriver($run($data, $fail) === 502, 'Send failure reported success');
    checkDriver($run(array_replace($data, ['ssn' => 'must-not-forward'])) === 200, 'Valid application rejected');
    checkDriver($run($data) === 200 && $calls === 1, 'Retry sent twice');
    checkDriver(!str_contains(file_get_contents($state), 'test@example.com'), 'Applicant data persisted in state');
    file_put_contents($state, '');
    for ($i = 0; $i < 5; $i++) checkDriver($run($data, $fail) === 502, 'Send failure not retryable');
    checkDriver($run($data) === 429, 'Rate limit not enforced');
    echo "PASS: owner-driver validation, consent, delivery, retries, private state, and rate limits\n";
} finally { unlink($state); }
