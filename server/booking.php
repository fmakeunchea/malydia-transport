<?php
declare(strict_types=1);

function validBooking(array $data): bool
{
    if (!empty($data['website'])) return false;
    $limits = ['firstName' => 80, 'lastName' => 80, 'phone' => 30, 'pickup' => 300, 'dropoff' => 300, 'date' => 10, 'time' => 5, 'mobility' => 20, 'returnRide' => 3, 'requestId' => 36];
    foreach ($limits as $key => $limit) {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '' || preg_match_all('/./us', $data[$key]) > $limit || preg_match('/[\x00-\x1f\x7f]/', $data[$key])) return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['date'], new DateTimeZone('America/New_York'));
    $today = new DateTimeImmutable('today', new DateTimeZone('America/New_York'));
    if (!$date || $date->format('Y-m-d') !== $data['date'] || $date < $today || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $data['time'])) return false;
    if (!in_array($data['mobility'], ['ambulatory', 'wheelchair', 'stretcher'], true) || !in_array($data['returnRide'], ['yes', 'no'], true)) return false;
    if (!preg_match('/^[a-f0-9]{8}(-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD', $data['requestId']) || strlen(preg_replace('/\D/', '', $data['phone'])) < 10) return false;
    if (isset($data['email']) && (!is_string($data['email']) || strlen($data['email']) > 254 || ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)))) return false;
    return true;
}

function bookingText(array $data): string
{
    return implode("\n", [
        'New ride request — contact the requester to confirm pricing and availability.',
        'Name: ' . $data['firstName'] . ' ' . $data['lastName'],
        'Phone: ' . $data['phone'], 'Email: ' . ($data['email'] ?: 'Not provided'),
        'Pickup: ' . $data['pickup'], 'Drop-off: ' . $data['dropoff'],
        'Pickup date/time (Virginia): ' . $data['date'] . ' ' . $data['time'],
        'Mobility: ' . $data['mobility'], 'Return ride: ' . $data['returnRide'],
        'This request is not a confirmed booking.',
    ]);
}

function writeBookingState($file, array $state): void
{
    $json = json_encode($state, JSON_THROW_ON_ERROR);
    rewind($file);
    if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) throw new RuntimeException('State unavailable');
}

// The injected sender lets tests exercise delivery failures without contacting Zoho.
function handleBooking(array $request, string $body, array $config, string $statePath, callable $send): int
{
    if (($request['REQUEST_METHOD'] ?? '') !== 'POST') return 405;
    if (!in_array($request['HTTP_ORIGIN'] ?? '', $config['origins'] ?? [], true)) return 403;
    if (!str_starts_with(strtolower($request['CONTENT_TYPE'] ?? ''), 'application/json')) return 415;
    if (strlen($body) > 6000) return 413;
    try { $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR); } catch (JsonException $error) { return 400; }
    if (!is_array($data) || !validBooking($data)) return 400;
    // Keep only accepted fields; no extra input can enter the email or deduplication state.
    $data = array_intersect_key($data, array_flip(['firstName', 'lastName', 'phone', 'email', 'pickup', 'dropoff', 'date', 'time', 'mobility', 'returnRide', 'requestId']));
    $data['email'] = $data['email'] ?? '';
    if (empty($config['smtp_host']) || empty($config['smtp_password']) || ($config['smtp_username'] ?? '') !== 'info@malydiahealth.com' || !in_array($config['smtp_port'] ?? 0, [465, 587], true)) return 503;
    // One small private state file holds only hashes/timestamps. Lock across send to avoid concurrent duplicate emails.
    $file = fopen($statePath, 'c+');
    if (!$file) return 503;
    chmod($statePath, 0600);
    if (!flock($file, LOCK_EX | LOCK_NB)) { fclose($file); return 503; }
    try {
        $raw = stream_get_contents($file);
        $state = $raw === '' ? ['sent' => [], 'attempts' => []] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($state['sent'] ?? null) || !is_array($state['attempts'] ?? null)) return 503;
        $now = time();
        $state['sent'] = array_filter($state['sent'], fn($time) => $time > $now - 86400);
        $state['attempts'] = array_map(fn($times) => array_values(array_filter($times, fn($time) => $time > $now - 60)), $state['attempts']);
        $state['attempts'] = array_filter($state['attempts']);
        $key = hash('sha256', $data['requestId'] . bookingText($data));
        if (isset($state['sent'][$key])) return 200;
        // Use the actual server-provided peer address, not a client-controlled forwarded header.
        $ip = hash('sha256', $request['REMOTE_ADDR'] ?? 'unknown');
        if (count($state['attempts'][$ip] ?? []) >= 5 || array_sum(array_map('count', $state['attempts'])) >= 50) return 429;
        $state['attempts'][$ip][] = $now;
        writeBookingState($file, $state);
        try { $send($data); } catch (Throwable $error) { return 502; }
        $state['sent'][$key] = $now;
        writeBookingState($file, $state);
        return 200;
    } catch (Throwable $error) {
        return 503;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

function sendBookingEmail(array $config, array $data): void
{
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->Port = $config['smtp_port'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_username'];
    $mail->Password = $config['smtp_password'];
    $mail->SMTPSecure = $config['smtp_port'] === 465 ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
    $mail->Timeout = 10;
    $mail->Timelimit = 15;
    $mail->SMTPDebug = 0;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom('info@malydiahealth.com', 'Malydia Healthcare Transportation');
    $mail->addAddress('info@malydiahealth.com');
    $mail->Subject = 'New website ride request';
    $mail->Body = bookingText($data);
    if (!$mail->send()) throw new RuntimeException('Delivery not accepted');
}
