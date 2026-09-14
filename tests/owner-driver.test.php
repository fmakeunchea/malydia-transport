<?php
declare(strict_types=1);
require __DIR__ . '/../server/owner-driver.php';
function checkDriver(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$config = ['origins' => ['https://example.com'], 'smtp_host' => 'smtp.example.com', 'smtp_port' => 465, 'smtp_username' => 'info@malydiahealth.com', 'smtp_password' => 'test-only'];
$request = ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'https://example.com', 'CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1'];
$data = ['fullName' => 'Test Applicant', 'city' => 'Spotsylvania', 'state' => 'VA', 'zip' => '22553', 'email' => 'test@example.com', 'phone' => '5405550100', 'licenseState' => 'VA', 'yearsLicensed' => '0', 'atLeast18' => 'No', 'validLicense' => 'No', 'hasExperience' => 'Yes', 'authorizeChecks' => 'No', 'completeTraining' => 'No', 'experience' => "Caregiver experience.\nPassenger transportation.", 'availability' => ['Weekday mornings', 'Sundays'], 'workInterest' => 'On-call', 'serviceAreas' => 'Stafford, Fredericksburg', 'onboardingStart' => 'In two weeks', 'useOwnVehicle' => 'Yes', 'ownsVehicle' => 'No', 'vehicleYear' => '2020', 'vehicleMake' => 'Example', 'vehicleModel' => 'Van', 'seatingCapacity' => '5', 'vehicleType' => 'Other', 'currentRegistration' => 'No', 'currentInsurance' => 'No', 'vehicleCondition' => 'No', 'assistPassengers' => 'No', 'followProcedures' => 'No', 'acknowledgment' => 'yes', 'requestId' => 'a1234567-1234-1234-1234-123456789012'];
$optionalNumbers = [12,24,26,27,28,29,34,37,38];
checkDriver(count(driverWaitlist()['fields']) === 38, 'Expected 38 questions');
foreach (driverWaitlist()['fields'] as $field) checkDriver($field['required'] === !in_array($field['number'], $optionalNumbers, true), 'Required flag differs from supplied form');
$state = tempnam(__DIR__, 'driver-test-');
$calls = 0;
$sender = function ($received) use (&$calls, $data): void {
    $calls++;
    foreach (['ssn','officeStatus','payRate','applicantSignature','licenseNumber','vin','streetAddress','consent'] as $key) checkDriver(!isset($received[$key]), 'Legacy or office field forwarded');
    $text = driverText($received);
    foreach (driverWaitlist()['fields'] as $field) checkDriver(str_contains($text, $field['number'] . '. ' . $field['label'] . ':'), 'Question missing from notification');
    foreach (['Spotsylvania','Weekday mornings','Sundays','On-call','In two weeks','including the driver?','CPR/First Aid certification?: Not provided',driverWaitlist()['acknowledgment'],'Reviewed by: ____'] as $value) checkDriver(str_contains($text, $value), 'Notification missing answer or acknowledgment');
    checkDriver(!str_contains($text,'attacker-reviewer'), 'Office review supplied by applicant');
};
$run = fn($payload, $send = null, $headers = null, $settings = null) => handleDriver($headers ?? $request, json_encode($payload), $settings ?? $config, $state, $send ?? $sender);
try {
    checkDriver(validDriver($data), 'Optional omissions or preliminary No answers incorrectly rejected');
    checkDriver(validDriver(array_replace($data, ['experience' => '', 'cprFirstAid' => 'Expired', 'defensiveDriving' => 'No', 'passengerAssistance' => 'Yes', 'referralSource' => 'Other'])), 'Optional choices rejected');
    foreach (driverWaitlist()['fields'] as $field) {
        if (!$field['required']) continue;
        $missing = $data; unset($missing[$field['name']]);
        checkDriver($run($missing) === 400, 'Missing required field accepted');
    }
    foreach (['acknowledgment' => 'no', 'email' => 'invalid', 'fullName' => '', 'phone' => '123', 'availability' => [], 'workInterest' => 'Flexible', 'seatingCapacity' => '0', 'vehicleYear' => '9999', 'vehicleType' => 'invalid', 'atLeast18' => 'invalid', 'website' => 'bot', 'yearsLicensed' => '2.5', 'zip' => 'invalid', 'cprFirstAid' => 'Current', 'defensiveDriving' => 'Expired', 'onboardingStart' => "\r\ninjected"] as $key => $value) checkDriver($run(array_replace($data, [$key => $value])) === 400, 'Invalid ' . $key . ' accepted');
    foreach ([[['nested']], ['Weekday mornings','Weekday mornings'], ['Weekdays']] as $value) checkDriver($run(array_replace($data, ['availability' => $value])) === 400, 'Invalid availability accepted');
    checkDriver($run($data, null, array_replace($request, ['HTTP_ORIGIN' => 'https://evil.example'])) === 403, 'Cross-origin request accepted');
    checkDriver($run($data, null, null, array_replace($config, ['smtp_password' => ''])) === 503, 'Missing configuration accepted');
    checkDriver(handleDriver($request, str_repeat(' ', 16001), $config, $state, $sender) === 413, 'Oversize request accepted');
    checkDriver($calls === 0, 'Invalid request sent');
    $fail = function (): void { throw new RuntimeException('SMTP unavailable'); };
    checkDriver($run($data, $fail) === 502, 'Send failure reported success');
    checkDriver($run(array_replace($data, ['ssn' => 'must-not-forward', 'officeStatus' => 'Active', 'payRate' => '999', 'applicantSignature' => 'retired', 'licenseNumber' => 'retired', 'vin' => 'retired', 'streetAddress' => 'retired', 'consent' => 'yes', 'reviewedBy' => 'attacker-reviewer'])) === 200, 'Valid submission rejected');
    checkDriver($run($data) === 200 && $calls === 1, 'Retry sent twice');
    $stored = file_get_contents($state);
    foreach (['email', 'fullName', 'phone'] as $key) checkDriver(!str_contains($stored, $data[$key]), 'Applicant details stored in state');
    file_put_contents($state, '');
    for ($i = 0; $i < 5; $i++) checkDriver($run($data, $fail) === 502, 'Send failure not retryable');
    checkDriver($run($data) === 429, 'Rate limit not enforced');
    echo "PASS: all 38 questions, exact required fields, optional omissions, No answers, acknowledgment, email contents, legacy field exclusion, retries, and rate limits\n";
} finally { unlink($state); }
