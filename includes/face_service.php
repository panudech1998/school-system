<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function face_service_unavailable_message(): string
{
    return 'ระบบค้นหาใบหน้ายังไม่ได้เปิด กรุณาดับเบิลคลิกไฟล์ START_FACE_SERVICE.bat ในโฟลเดอร์ SWK_Phonto แล้วรอจนเห็นข้อความ Running on http://127.0.0.1:5055 จากนั้นลองใหม่';
}

function face_health(): bool
{
    $curl = curl_init(rtrim(FACE_SERVICE_URL, '/') . '/health');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return $body !== false && $status === 200;
}

function face_index_photo(int $eventId, int $photoId, string $path): array
{
    $payload = json_encode([
        'event_id' => $eventId,
        'photo_id' => $photoId,
        'path' => $path,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $curl = curl_init(rtrim(FACE_SERVICE_URL, '/') . '/index');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Service-Token: ' . FACE_SERVICE_TOKEN,
        ],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 300,
    ]);

    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlNumber = curl_errno($curl);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($body === false || $curlNumber === CURLE_COULDNT_CONNECT) {
        throw new RuntimeException(face_service_unavailable_message());
    }

    if ($status >= 400) {
        $decoded = json_decode((string) $body, true);
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';
        throw new RuntimeException($message !== '' ? $message : ($curlError !== '' ? $curlError : 'Face Service ประมวลผลไม่สำเร็จ'));
    }

    return json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
}
