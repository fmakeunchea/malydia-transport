<?php
declare(strict_types=1);
require_once __DIR__ . '/booking.php';

function driverWaitlist(): array
{
    static $schema = null;
    if ($schema === null) $schema = json_decode(file_get_contents(__DIR__ . '/driver-waitlist.json'), true, 32, JSON_THROW_ON_ERROR);
    return $schema;
}
function driverFields(): array
{
    return [...array_column(driverWaitlist()['fields'], 'name'), 'acknowledgment', 'requestId'];
}
function validDriver(array $data): bool
{
    if (!empty($data['website']) || ($data['acknowledgment'] ?? '') !== 'yes' || !is_string($data['requestId'] ?? null) || !preg_match('/^[a-f0-9]{8}(-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD', $data['requestId'])) return false;
    foreach (driverWaitlist()['fields'] as $field) {
        $value = $data[$field['name']] ?? null;
        if ($field['type'] === 'multiple') {
            if (!is_array($value) || !array_is_list($value) || count($value) < 1 || count($value) > count($field['options'])) return false;
            foreach ($value as $item) if (!in_array($item, $field['options'], true)) return false;
            if (count(array_unique($value)) !== count($value)) return false;
            continue;
        }
        if ($value === null || $value === '') { if ($field['required']) return false; else continue; }
        if (!is_string($value) || trim($value) === '') return false;
        if (isset($field['options'])) {
            if (!in_array($value, $field['options'], true)) return false;
        } elseif ($field['type'] === 'number') {
            $max = $field['max'] === 'nextYear' ? (int)date('Y') + 1 : $field['max'];
            if (!ctype_digit($value) || (int)$value < $field['min'] || (int)$value > $max) return false;
        } else {
            if (preg_match_all('/./us', $value) > $field['maxLength']) return false;
            if (preg_match($field['type'] === 'textarea' ? '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/' : '/[\x00-\x1f\x7f]/', $value)) return false;
            if ($field['type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) return false;
            if ($field['type'] === 'tel' && strlen(preg_replace('/\D/', '', $value)) < 10) return false;
            if ($field['name'] === 'zip' && !preg_match('/^\d{5}(-\d{4})?$/D', $value)) return false;
        }
    }
    return true;
}
function driverText(array $data): string
{
    $schema = driverWaitlist();
    $lines = ['MALYDIA HEALTHCARE TRANSPORTATION LLC', 'DRIVER WAITING LIST — EXPRESSION OF INTEREST', 'This submission is not an approval or a guarantee of work.'];
    foreach ($schema['groups'] as $group) {
        $lines[] = "\n" . strtoupper($group['title']);
        foreach ($schema['fields'] as $field) {
            if ($field['number'] < $group['start'] || $field['number'] > $group['end']) continue;
            $value = $data[$field['name']] ?? '';
            if (is_array($value)) { sort($value); $value = implode(', ', $value); }
            if (isset($field['visibleWhen']) && ($data[$field['visibleWhen']['field']] ?? '') !== $field['visibleWhen']['value']) $value = 'Not applicable';
            $lines[] = $field['number'] . '. ' . $field['label'] . ': ' . ($value !== '' ? $value : 'Not provided');
        }
    }
    $lines[] = "\nREQUIRED ACKNOWLEDGMENT";
    $lines[] = $schema['acknowledgment'];
    $lines[] = 'Applicant checked the required acknowledgment: yes';
    // Staff materials stay separate from the applicant's answers; no applicant fields set office status or pay.
    $office = json_decode(file_get_contents(__DIR__ . '/driver-options.json'), true, 32, JSON_THROW_ON_ERROR);
    $lines[] = "\nMALYDIA ONBOARDING CHECKLIST — OFFICE USE ONLY";
    foreach ($office['office'] as $label) $lines[] = '[ ] ' . $label;
    $lines[] = 'Status (office to assign): ' . implode('  ', array_map(fn($status) => '[ ] ' . $status, $office['officeStatuses']));
    $internalPath = __DIR__ . '/driver-internal.txt';
    $internal = file_get_contents(is_file($internalPath) ? $internalPath : __DIR__ . '/driver-internal.example.txt');
    if ($internal === false) throw new RuntimeException('Internal review template unavailable');
    $lines[] = "\n" . $internal;
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
