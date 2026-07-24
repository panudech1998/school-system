<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

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
    verify_csrf();
    if (($_POST['consent'] ?? '') !== '1') {
        fail_json('กรุณายอมรับการประมวลผลใบหน้า');
    }

    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $stmt = db()->prepare('SELECT id, face_threshold FROM events WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event) {
        fail_json('ไม่พบกิจกรรม', 404);
    }

    if (!isset($_FILES['selfie']) || $_FILES['selfie']['error'] !== UPLOAD_ERR_OK) {
        fail_json('กรุณาเลือกรูปใบหน้าที่สมบูรณ์');
    }
    if ((int) $_FILES['selfie']['size'] > MAX_UPLOAD_BYTES) {
        fail_json('ไฟล์มีขนาดใหญ่เกินกำหนด');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['selfie']['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        fail_json('รองรับเฉพาะ JPG, PNG และ WebP');
    }

    $post = [
        'event_id' => (string) $event['id'],
        'threshold' => (string) $event['face_threshold'],
        'selfie' => new CURLFile($_FILES['selfie']['tmp_name'], $mime, 'selfie.' . $allowed[$mime]),
    ];
    $ch = curl_init(rtrim(FACE_SERVICE_URL, '/') . '/search');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $status >= 400) {
        fail_json('บริการค้นหาใบหน้าไม่พร้อมใช้งาน: ' . ($error ?: $body), 503);
    }

    $matchData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $matches = $matchData['matches'] ?? [];
    $similarities = [];
    foreach ($matches as $match) {
        $photoId = (int) ($match['photo_id'] ?? 0);
        $similarity = (float) ($match['similarity'] ?? 0);
        if ($photoId > 0 && $similarity >= (float) $event['face_threshold']) {
            $similarities[$photoId] = max($similarities[$photoId] ?? 0, $similarity);
        }
    }

    $results = [];
    if ($similarities) {
        $ids = array_keys($similarities);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([(int) $event['id']], $ids);
        $stmt = db()->prepare("SELECT id, file_name, local_path FROM photos WHERE event_id = ? AND is_visible = 1 AND id IN ($placeholders)");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $photo) {
            $results[] = [
                'id' => (int) $photo['id'],
                'file_name' => $photo['file_name'],
                'similarity' => $similarities[(int) $photo['id']],
                'image_url' => url($photo['local_path']),
                'download_url' => url('download.php?id=' . $photo['id']),
            ];
        }
        usort($results, fn(array $a, array $b): int => $b['similarity'] <=> $a['similarity']);
    }

    $log = db()->prepare('INSERT INTO face_search_logs (event_id, result_count, threshold, ip_hash, created_at) VALUES (?, ?, ?, ?, NOW())');
    $log->execute([
        $event['id'],
        count($results),
        $event['face_threshold'],
        hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . session_id()),
    ]);

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    fail_json($e->getMessage(), 500);
}
