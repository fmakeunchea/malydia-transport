<?php
declare(strict_types=1);
require __DIR__ . '/../server/owner-driver.php';
function checkDriver(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$config = ['origins' => ['https://example.com'], 'smtp_host' => 'smtp.example.com', 'smtp_port' => 465, 'smtp_username' => 'info@malydiahealth.com', 'smtp_password' => 'test-only'];
$request = ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'https://example.com', 'CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1'];
$data = ['fullName' => 'Test Applicant', 'streetAddress' => '100 Example Street', 'city' => 'Spotsylvania', 'state' => 'VA', 'zip' => '22553', 'email' => 'test@example.com', 'phone' => '5405550100', 'licenseState' => 'VA', 'licenseNumber' => 'TEST-LICENSE-ONLY', 'licenseExpiration' => '2030-01-01', 'yearsLicensed' => '5', 'atLeast18' => 'Yes', 'licensedTwoYears' => 'Yes', 'authorizeChecks' => 'Yes', 'completeTraining' => 'Yes', 'experience' => "Caregiver experience.\nPassenger transportation.", 'availability' => ['Weekdays', 'Part-time'], 'serviceAreas' => 'Stafford, Fredericksburg', 'vehicleAuthority' => 'Yes', 'vehicleYear' => '2020', 'vehicleMake' => 'Example', 'vehicleModel' => 'Van', 'vin' => 'TESTVIN1234567890', 'licensePlate' => 'TESTPLATE / VA', 'mileage' => '45000', 'seatingCapacity' => '5', 'vehicleType' => 'Minivan', 'wheelchairAccessible' => 'No', 'currentRegistration' => 'Yes', 'currentInspection' => 'Yes', 'currentInsurance' => 'Yes', 'consent' => 'yes', 'requestId' => 'a1234567-1234-1234-1234-123456789012'];
$state = tempnam(__DIR__, 'driver-test-');
$calls = 0;
$sender = function ($received) use (&$calls, $data): void {
    $calls++;
    checkDriver(!isset($received['ssn']), 'Extra data forwarded');
    $text = driverText($received);
    foreach (driverLabels() as $key => $label) {
        if ($key !== 'vehicleOther') checkDriver(str_contains($text, $label . ': ' . $data[$key]), 'Missing field ' . $key);
    }
    checkDriver(str_contains($text, 'Part-time, Weekdays') && str_contains($text, 'consideration: yes'), 'Missing availability/consent');
    checkDriver(str_contains($text, 'INCLUDING DRIVER') && str_contains($text, 'not automatically sufficient'), 'Missing seating distinction or notice');
};
$run = fn($payload, $send = null, $headers = null, $settings = null) => handleDriver($headers ?? $request, json_encode($payload), $settings ?? $config, $state, $send ?? $sender);
try {
    foreach (['consent' => 'no', 'email' => 'invalid', 'fullName' => '', 'phone' => '123', 'availability' => [], 'seatingCapacity' => '0', 'vehicleYear' => '9999', 'vehicleType' => 'invalid', 'atLeast18' => 'invalid', 'website' => 'bot', 'licenseNumber' => "\r\ninjected", 'licenseExpiration' => '2030-02-30', 'mileage' => '-1', 'yearsLicensed' => '2.5', 'vin' => 'bad!', 'zip' => 'invalid'] as $key => $value) checkDriver($run(array_replace($data, [$key => $value])) === 400, 'Invalid ' . $key . ' accepted');
    foreach (driverFields() as $key) {
        if ($key === 'vehicleOther') continue;
        $missing = $data; unset($missing[$key]);
        checkDriver($run($missing) === 400, 'Missing ' . $key . ' accepted');
    }
    checkDriver($run(array_replace($data, ['availability' => [['nested']]])) === 400, 'Nested availability accepted');
    checkDriver($run(array_replace($data, ['vehicleType' => 'Other'])) === 400, 'Other vehicle description not required');
    checkDriver(validDriver(array_replace($data, ['vehicleType' => 'Other', 'vehicleOther' => 'Wagon'])), 'Other vehicle rejected');
    checkDriver(validDriver(array_replace($data, ['atLeast18' => 'No', 'licensedTwoYears' => 'No', 'authorizeChecks' => 'No', 'completeTraining' => 'No', 'vehicleAuthority' => 'No', 'yearsLicensed' => '0', 'licenseExpiration' => '2020-01-01', 'currentInsurance' => 'No'])), 'Preliminary No answers incorrectly rejected');
    checkDriver($run($data, null, array_replace($request, ['HTTP_ORIGIN' => 'https://evil.example'])) === 403, 'Cross-origin request accepted');
    checkDriver($run($data, null, null, array_replace($config, ['smtp_password' => ''])) === 503, 'Missing configuration accepted');
    checkDriver(handleDriver($request, str_repeat(' ', 16001), $config, $state, $sender) === 413, 'Oversize request accepted');
    checkDriver($calls === 0, 'Invalid request sent');
    $fail = function (): void { throw new RuntimeException('SMTP unavailable'); };
    checkDriver($run($data, $fail) === 502, 'Send failure reported success');
    checkDriver($run(array_replace($data, ['ssn' => 'must-not-forward'])) === 200, 'Valid application rejected');
    checkDriver($run($data) === 200 && $calls === 1, 'Retry sent twice');
    $stored = file_get_contents($state);
    foreach (['email', 'fullName', 'streetAddress', 'licenseNumber', 'vin', 'licensePlate'] as $key) checkDriver(!str_contains($stored, $data[$key]), 'Applicant details stored in state');
    file_put_contents($state, '');
    for ($i = 0; $i < 5; $i++) checkDriver($run($data, $fail) === 502, 'Send failure not retryable');
    checkDriver($run($data) === 429, 'Rate limit not enforced');
    echo "PASS: complete application fields, preliminary No answers, validation, consent, delivery, retries, private state, and rate limits\n";
} finally { unlink($state); }
