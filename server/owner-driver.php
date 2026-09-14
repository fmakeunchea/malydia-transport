<?php
declare(strict_types=1);
require_once __DIR__ . '/booking.php';

function driverOptions(): array
{
    static $options = null;
    if ($options === null) $options = json_decode(file_get_contents(__DIR__ . '/driver-options.json'), true, 32, JSON_THROW_ON_ERROR);
    return $options;
}

function driverLabels(): array
{
    return [
        'fullName' => 'Full legal name', 'streetAddress' => 'Street address', 'city' => 'City', 'state' => 'State', 'zip' => 'ZIP', 'phone' => 'Phone number', 'email' => 'Email address',
        'licenseState' => 'Driver license state', 'licenseNumber' => 'Driver license number', 'licenseExpiration' => 'License expiration date', 'yearsLicensed' => 'Years continuously licensed',
        'atLeast18' => 'At least 18 years old', 'licensedTwoYears' => 'Valid license for at least two years', 'authorizeChecks' => 'Willing to authorize motor-vehicle-record and background/credential checks', 'completeTraining' => 'Willing to complete NEMT/safety training',
        'experience' => 'Relevant driving/NEMT/healthcare/DSP/caregiver/transportation experience', 'serviceAreas' => 'Preferred service areas',
        'vehicleAuthority' => 'Owns or has lawful authority to use vehicle', 'vehicleYear' => 'Vehicle year', 'vehicleMake' => 'Vehicle make', 'vehicleModel' => 'Vehicle model', 'vin' => 'VIN', 'licensePlate' => 'License plate/state', 'mileage' => 'Current mileage', 'seatingCapacity' => 'Seating capacity INCLUDING DRIVER', 'vehicleType' => 'Vehicle type', 'vehicleOther' => 'Other vehicle type',
        'wheelchairAccessible' => 'Wheelchair accessible', 'currentRegistration' => 'Current registration', 'currentInspection' => 'Current inspection (if applicable)', 'currentInsurance' => 'Current auto insurance',
    ];
}
function driverFields(): array
{
    return [...array_keys(driverLabels()), 'availability', 'safetyChecks', ...array_keys(driverOptions()['credentials']), 'otherCertifications', 'acknowledgment', 'applicantSignature', 'printedName', 'signatureDate', 'consent', 'requestId'];
}
function validDriver(array $data): bool
{
    if (!empty($data['website']) || ($data['consent'] ?? '') !== 'yes' || ($data['acknowledgment'] ?? '') !== 'yes') return false;
    $limits = ['applicantSignature' => 160, 'printedName' => 160, 'signatureDate' => 10, 'fullName' => 160, 'streetAddress' => 200, 'city' => 100, 'state' => 60, 'zip' => 10, 'phone' => 30, 'email' => 254, 'licenseState' => 60, 'licenseNumber' => 40, 'licenseExpiration' => 10, 'experience' => 1000, 'serviceAreas' => 300, 'vehicleMake' => 60, 'vehicleModel' => 60, 'vin' => 17, 'licensePlate' => 60, 'requestId' => 36];
    foreach ($limits as $key => $limit) {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '' || preg_match_all('/./us', $data[$key]) > $limit) return false;
        // A plain-text experience paragraph may contain line breaks; other inputs must be single-line.
        if (preg_match($key === 'experience' ? '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/' : '/[\x00-\x1f\x7f]/', $data[$key])) return false;
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen(preg_replace('/\D/', '', $data['phone'])) < 10 || !preg_match('/^[a-f0-9]{8}(-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD', $data['requestId'])) return false;
    if (!preg_match('/^\d{5}(-\d{4})?$/D', $data['zip']) || !preg_match('/^[A-Za-z0-9]{6,17}$/D', $data['vin'])) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['licenseExpiration']);
    if (!$date || $date->format('Y-m-d') !== $data['licenseExpiration']) return false;
    $signed = DateTimeImmutable::createFromFormat('!Y-m-d', $data['signatureDate']);
    if (!$signed || $signed->format('Y-m-d') !== $data['signatureDate']) return false;
    foreach (['atLeast18', 'licensedTwoYears', 'authorizeChecks', 'completeTraining', 'vehicleAuthority', 'wheelchairAccessible', 'currentRegistration', 'currentInspection', 'currentInsurance'] as $key) {
        if (!in_array($data[$key] ?? null, ['Yes', 'No'], true)) return false;
    }
    if (!isset($data['availability']) || !is_array($data['availability']) || !array_is_list($data['availability']) || count($data['availability']) < 1 || count($data['availability']) > 5) return false;
    foreach ($data['availability'] as $value) if (!in_array($value, ['Weekdays', 'Evenings', 'Weekends', 'Full-time', 'Part-time'], true)) return false;
    if (count(array_unique($data['availability'])) !== count($data['availability'])) return false;
    if (!in_array($data['vehicleType'] ?? null, ['Sedan', 'SUV', 'Minivan', 'Van', 'Other'], true)) return false;
    if (isset($data['vehicleOther']) && (!is_string($data['vehicleOther']) || preg_match_all('/./us', $data['vehicleOther']) > 100 || preg_match('/[\x00-\x1f\x7f]/', $data['vehicleOther']))) return false;
    if ($data['vehicleType'] === 'Other' && trim($data['vehicleOther'] ?? '') === '') return false;
    foreach (['vehicleYear' => [1900, (int)date('Y') + 1], 'seatingCapacity' => [1, 100], 'yearsLicensed' => [0, 100], 'mileage' => [0, 9999999]] as $key => [$min, $max]) {
        if (!isset($data[$key]) || !is_string($data[$key]) || !ctype_digit($data[$key]) || (int)$data[$key] < $min || (int)$data[$key] > $max) return false;
    }
    $options = driverOptions();
    if (!isset($data['safetyChecks']) || !is_array($data['safetyChecks']) || !array_is_list($data['safetyChecks']) || count($data['safetyChecks']) > count($options['safety'])) return false;
    foreach ($data['safetyChecks'] as $value) if (!is_string($value) || !array_key_exists($value, $options['safety'])) return false;
    if (count(array_unique($data['safetyChecks'])) !== count($data['safetyChecks'])) return false;
    foreach ($options['credentials'] as $key => $label) if (!in_array($data[$key] ?? null, $options['trainingStatuses'], true)) return false;
    if (isset($data['otherCertifications']) && (!is_string($data['otherCertifications']) || preg_match_all('/./us', $data['otherCertifications']) > 500 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $data['otherCertifications']))) return false;
    return true;
}
function driverText(array $data): string
{
    $lines = ['MALYDIA HEALTHCARE TRANSPORTATION LLC', 'OWNER-DRIVER APPLICATION & WAITING LIST', 'Ambulatory Non-Emergency Medical Transportation (NEMT) - Virginia', 'Preliminary information only. Submission does not guarantee employment, independent-contractor status, trip assignments, or approval to transport passengers.'];
    foreach (driverLabels() as $key => $label) {
        if ($key === 'fullName') $lines[] = "\n1. APPLICANT INFORMATION";
        if ($key === 'licenseState') $lines[] = "\n2. DRIVER INFORMATION";
        if ($key === 'vehicleAuthority') $lines[] = "\n3. OWNER-DRIVER VEHICLE INFORMATION";
        if ($key === 'vehicleOther' && $data['vehicleType'] !== 'Other') continue;
        $lines[] = $label . ': ' . ($data[$key] ?? 'Not provided');
        if ($key === 'experience') {
            $availability = $data['availability'];
            sort($availability);
            $lines[] = 'Availability: ' . implode(', ', $availability);
        }
    }
    $lines[] = "\nImportant: A personal auto policy or ordinary registration is not automatically sufficient for paid NEMT service. No applicant may use a vehicle for Malydia trips until Malydia confirms applicable operating-authority, registration/plate, insurance, broker/provider, inspection, and credentialing requirements.";
    $options = driverOptions();
    $lines[] = "\n4. PRELIMINARY VEHICLE SAFETY CHECKLIST";
    $lines[] = 'Applicant-reported only. Unchecked means not confirmed, not a completed inspection.';
    foreach ($options['safety'] as $key => $label) $lines[] = (in_array($key, $data['safetyChecks'], true) ? '[x] ' : '[ ] ') . $label;
    $lines[] = "\n5. TRAINING / CREDENTIALS";
    foreach ($options['credentials'] as $key => $label) $lines[] = $label . ': ' . $data[$key];
    $lines[] = 'Other relevant certifications: ' . (trim($data['otherCertifications'] ?? '') ?: 'Not provided');
    $lines[] = 'Applicant agreed to contact and waiting-list consideration: yes';
    $lines[] = "\n6. MALYDIA ONBOARDING CHECKLIST - OFFICE USE";
    $lines[] = 'For staff completion only. No verification, approval, or activation is implied by this submission.';
    foreach ($options['office'] as $label) $lines[] = '[ ] ' . $label;
    $lines[] = 'Status (office to assign): ' . implode('  ', array_map(fn($status) => '[ ] ' . $status, $options['officeStatuses']));
    $program = json_decode(file_get_contents(__DIR__ . '/driver-program.json'), true, 32, JSON_THROW_ON_ERROR);
    $lines[] = "\n7. OWNER-DRIVER PROGRAM FRAMEWORK";
    $lines[] = 'Proposed model: ' . $program['model'];
    $internalPath = __DIR__ . '/driver-internal.txt';
    $internal = file_get_contents(is_file($internalPath) ? $internalPath : __DIR__ . '/driver-internal.example.txt');
    if ($internal === false) throw new RuntimeException('Internal review template unavailable');
    $lines[] = "\n" . $internal;
    $lines[] = "\n9. APPLICANT ACKNOWLEDGMENT";
    $lines[] = $program['acknowledgment'];
    $lines[] = 'Applicant acknowledged the statement: yes';
    $lines[] = 'Applicant signature (typed): ' . $data['applicantSignature'];
    $lines[] = 'Printed name: ' . $data['printedName'];
    $lines[] = 'Date: ' . $data['signatureDate'];
    $lines[] = "\nMalydia Healthcare Transportation LLC - Internal Review";
    $lines[] = 'Reviewed by: ______________________________';
    $lines[] = 'Date: ____________________________________';
    $lines[] = 'Notes: ___________________________________';
    return implode("\n", $lines);
}
function handleDriver(array $request, string $body, array $config, string $statePath, callable $send): int
{
    return handleBooking($request, $body, $config, $statePath, $send, 'validDriver', 'driverText', driverFields(), 16000);
}
