<?php
declare(strict_types=1);
require_once __DIR__ . '/booking.php';

function driverFields(): array
{
    return ['firstName', 'lastName', 'phone', 'email', 'city', 'areas', 'availability', 'availabilityDetails', 'vehicleAccess', 'vehicleMake', 'vehicleModel', 'vehicleYear', 'seats', 'vehicleType', 'licenseStatus', 'insuranceStatus', 'experience', 'consent', 'requestId'];
}
function validDriver(array $data): bool
{
    if (!empty($data['website']) || ($data['consent'] ?? '') !== 'yes') return false;
    $limits = ['firstName' => 80, 'lastName' => 80, 'phone' => 30, 'email' => 254, 'city' => 100, 'vehicleMake' => 60, 'vehicleModel' => 60, 'requestId' => 36];
    foreach ($limits as $key => $limit) {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '' || preg_match_all('/./us', $data[$key]) > $limit || preg_match('/[\x00-\x1f\x7f]/', $data[$key])) return false;
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen(preg_replace('/\D/', '', $data['phone'])) < 10 || !preg_match('/^[a-f0-9]{8}(-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD', $data['requestId'])) return false;
    if (!isset($data['areas']) || !is_array($data['areas']) || !array_is_list($data['areas']) || count($data['areas']) < 1 || count($data['areas']) > 4) return false;
    foreach ($data['areas'] as $area) if (!in_array($area, ['Fredericksburg', 'Spotsylvania', 'Stafford', 'Caroline'], true)) return false;
    if (count(array_unique($data['areas'])) !== count($data['areas'])) return false;
    $choices = [
        'availability' => ['Weekdays', 'Evenings', 'Weekends', 'Flexible'],
        'vehicleAccess' => ['I own a vehicle', 'I lease a vehicle', 'I am planning to obtain a vehicle'],
        'vehicleType' => ['Sedan', 'SUV', 'Minivan', 'Passenger van', 'Wheelchair-accessible vehicle', 'Other'],
        'licenseStatus' => ['Yes', 'No'],
        'insuranceStatus' => ['Personal auto coverage', 'Commercial auto coverage', 'No current coverage', 'Unsure'],
        'experience' => ['New to passenger transportation', 'Less than 1 year', '1–3 years', 'More than 3 years'],
    ];
    foreach ($choices as $key => $options) if (!in_array($data[$key] ?? null, $options, true)) return false;
    foreach (['vehicleYear' => [1900, (int)date('Y') + 1], 'seats' => [1, 30]] as $key => [$min, $max]) {
        if (!isset($data[$key]) || !is_string($data[$key]) || !ctype_digit($data[$key]) || (int)$data[$key] < $min || (int)$data[$key] > $max) return false;
    }
    if (isset($data['availabilityDetails']) && (!is_string($data['availabilityDetails']) || preg_match_all('/./us', $data['availabilityDetails']) > 300 || preg_match('/[\x00-\x1f\x7f]/', $data['availabilityDetails']))) return false;
    return true;
}
function driverText(array $data): string
{
    $labels = ['firstName' => 'First name', 'lastName' => 'Last name', 'email' => 'Email', 'phone' => 'Phone', 'city' => 'Home city/county', 'availability' => 'Availability', 'availabilityDetails' => 'Availability details', 'vehicleAccess' => 'Vehicle access', 'vehicleMake' => 'Vehicle make', 'vehicleModel' => 'Vehicle model', 'vehicleYear' => 'Vehicle year', 'seats' => 'Passenger seats', 'vehicleType' => 'Vehicle type', 'licenseStatus' => 'Valid license (self-reported)', 'insuranceStatus' => 'Insurance (self-reported)', 'experience' => 'Passenger transportation experience'];
    $lines = ['Owner-driver application — waiting-list review', 'Review and retain this application in the company mailbox for future opportunities. This is not an approval or an offer of work.'];
    foreach ($labels as $key => $label) $lines[] = $label . ': ' . ($data[$key] ?? 'Not provided');
    $areas = $data['areas'];
    sort($areas);
    $lines[] = 'Service areas: ' . implode(', ', $areas);
    $lines[] = 'Applicant agreed to contact and waiting-list consideration: yes';
    return implode("\n", $lines);
}
function handleDriver(array $request, string $body, array $config, string $statePath, callable $send): int
{
    return handleBooking($request, $body, $config, $statePath, $send, 'validDriver', 'driverText', driverFields());
}
