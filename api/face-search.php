<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/face_service.php';

header('Content-Type: application/json; charset=utf-8');

function fail_json(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail_json('Method not allowed', 405);
}

try {
    $token = (string) ($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
        fail_json('เซสชันหมดอายุ กรุณาเปิดหน้าใหม่', 419);
    }

    if (($_POST['consent'] ?? '') !== '1') {
        fail_json('กรุณายอมรับการประมวลผลใบหน้า');
    }

    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $statement = db()->prepare('SELECT id, face_threshold FROM events WHERE id = ? AND is_active = 1 LIMIT 1');
    $statement->execute([$eventId]);
    $event = $statement->fetch();
    if (!$event) {
        fail_json('ไม่พบกิจกรรม', 404);
    }

    if (!isset($_FILES['selfie']) || $_FILES['selfie']['error'] !== UPLOAD_ERR_OK) {
        fail_json('กรุณาเลือกรูปใบหน้า');
    }
    if ((int) $_FILES['selfie']['size'] > MAX_UPLOAD_BYTES) {
        fail_json('ไฟล์ใหญ่เกินกำหนด');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['selfie']['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        fail_json('รองรับเฉพาะ JPG, PNG และ WebP');
    }

    $post = [
        'event_id' => (string) $eventId,
        'threshold' => (string) $event['face_threshold'],
        'selfie' => new CURLFile($_FILES['selfie']['tmp_name'], $mime, 'selfie.' . $allowed[$mime]),
    ];

    $curl = curl_init(rtrim(FACE_SERVICE_URL, '/') . '/search');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Service-Token: ' . FACE_SERVICE_TOKEN],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 180,
    ]);

    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlNumber = curl_errno($curl);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($body === false || $curlNumber === CURLE_COULDNT_CONNECT) {
        fail_json(face_service_unavailable_message(), 503);
    }

    if ($status >= 400) {
        $decoded = json_decode((string) $body, true);
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';
        fail_json($message !== '' ? $message : ($curlError !== '' ? $curlError : 'ระบบค้นหาใบหน้าประมวลผลไม่สำเร็จ'), 503);
    }

    $decodedBody = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
    $matches = $decodedBody['matches'] ?? [];
    $threshold = (float) $event['face_threshold'];
    $scores = [];

    foreach ($matches as $match) {
        $photoId = (int) ($match['photo_id'] ?? 0);
        $score = (float) ($match['similarity'] ?? 0);
        if ($photoId > 0 && $score >= $threshold) {
            $scores[$photoId] = max($scores[$photoId] ?? 0, $score);
        }
    }

    $results = [];
    if ($scores) {
        $ids = array_keys($scores);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = db()->prepare("SELECT id, file_name, local_path FROM photos WHERE event_id = ? AND is_visible = 1 AND face_indexed = 1 AND id IN ($placeholders)");
        $statement->execute(array_merge([(int) $eventId], $ids));

        foreach ($statement->fetchAll() as $photo) {
            $photoId = (int) $photo['id'];
            if (($scores[$photoId] ?? 0) < $threshold) {
                continue;
            }
            $results[] = [
                'id' => $photoId,
                'file_name' => $photo['file_name'],
                'similarity' => $scores[$photoId],
                'image_url' => url($photo['local_path']),
                'download_url' => absolute_url('download.php?id=' . $photoId),
            ];
        }

        usort($results, static fn(array $left, array $right): int => $right['similarity'] <=> $left['similarity']);
    }

    db()->prepare('INSERT INTO face_search_logs(event_id, result_count, threshold, ip_hash, created_at) VALUES(?,?,?,?,NOW())')
        ->execute([$eventId, count($results), $threshold, hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''))]);

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    fail_json($exception->getMessage(), 500);
}
