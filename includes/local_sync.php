<?php

declare(strict_types=1);

require_once __DIR__ . '/face_service.php';

/**
 * @return list<string>
 */
function local_image_files(string $folder): array
{
    $files = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if (!in_array(strtolower($file->getExtension()), $allowedExtensions, true)) {
            continue;
        }
        $files[] = $file->getPathname();
    }

    natcasesort($files);
    return array_values($files);
}

/**
 * @return array{discovered:int,updated:int,indexed:int,unchanged:int,hidden:int,failed:list<string>}
 */
function sync_event_folder(array $event, bool $indexFaces = true): array
{
    $eventId = (int) ($event['id'] ?? 0);
    $sourceFolder = trim((string) ($event['drive_folder_id'] ?? ''));

    if ($eventId <= 0) {
        throw new RuntimeException('รหัสกิจกรรมไม่ถูกต้อง');
    }
    if ($sourceFolder === '' || !is_dir($sourceFolder)) {
        throw new RuntimeException('ไม่พบโฟลเดอร์รูป กรุณาเปิด Google Drive for desktop และตรวจตำแหน่งโฟลเดอร์');
    }
    if (!is_readable($sourceFolder)) {
        throw new RuntimeException('ระบบไม่มีสิทธิ์อ่านโฟลเดอร์รูป');
    }

    $sourceFiles = local_image_files($sourceFolder);
    $destinationDirectory = STORAGE_PATH . '/photos/' . $eventId;
    if (!is_dir($destinationDirectory)
        && !mkdir($destinationDirectory, 0775, true)
        && !is_dir($destinationDirectory)) {
        throw new RuntimeException('สร้างโฟลเดอร์เก็บรูปไม่สำเร็จ');
    }

    $mimeExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $result = [
        'discovered' => 0,
        'updated' => 0,
        'indexed' => 0,
        'unchanged' => 0,
        'hidden' => 0,
        'failed' => [],
    ];
    $seenSourceIds = [];

    $existingStatement = db()->prepare(
        'SELECT id, drive_modified_at, file_size, face_indexed
         FROM photos WHERE event_id = ? AND drive_file_id = ? LIMIT 1'
    );
    $insertStatement = db()->prepare(
        'INSERT INTO photos(
            event_id, drive_file_id, file_name, mime_type, local_path,
            file_size, drive_modified_at, is_visible, face_indexed,
            created_at, updated_at
         ) VALUES(?,?,?,?,?,?,?,1,0,NOW(),NOW())'
    );
    $updateStatement = db()->prepare(
        'UPDATE photos
         SET file_name = ?, mime_type = ?, local_path = ?, file_size = ?,
             drive_modified_at = ?, is_visible = 1, face_indexed = ?, updated_at = NOW()
         WHERE id = ?'
    );
    $findIdStatement = db()->prepare(
        'SELECT id FROM photos WHERE event_id = ? AND drive_file_id = ? LIMIT 1'
    );
    $indexStatusStatement = db()->prepare('UPDATE photos SET face_indexed = ? WHERE id = ?');

    foreach ($sourceFiles as $sourcePath) {
        $realSource = realpath($sourcePath) ?: $sourcePath;
        $mime = (string) $finfo->file($realSource);
        if (!isset($mimeExtensions[$mime])) {
            continue;
        }

        $result['discovered']++;
        $normalizedPath = strtolower(str_replace('\\', '/', $realSource));
        $sourceId = hash('sha256', $normalizedPath);
        $seenSourceIds[] = $sourceId;
        $extension = $mimeExtensions[$mime];
        $relativePath = 'storage/photos/' . $eventId . '/' . $sourceId . '.' . $extension;
        $absolutePath = dirname(__DIR__) . '/' . $relativePath;
        $modifiedTimestamp = filemtime($realSource) ?: time();
        $modifiedAt = date('Y-m-d H:i:s', $modifiedTimestamp);
        $fileSize = filesize($realSource) ?: 0;

        $existingStatement->execute([$eventId, $sourceId]);
        $existing = $existingStatement->fetch();
        $needsCopy = !$existing
            || !is_file($absolutePath)
            || (string) $existing['drive_modified_at'] !== $modifiedAt
            || (int) $existing['file_size'] !== $fileSize;

        if ($needsCopy) {
            $temporaryPath = $absolutePath . '.tmp';
            if (!copy($realSource, $temporaryPath)) {
                $result['failed'][] = 'คัดลอกรูปไม่สำเร็จ: ' . basename($realSource);
                continue;
            }
            if (!@rename($temporaryPath, $absolutePath)) {
                @unlink($temporaryPath);
                $result['failed'][] = 'บันทึกรูปไม่สำเร็จ: ' . basename($realSource);
                continue;
            }
        }

        if ($existing) {
            $photoId = (int) $existing['id'];
            $faceIndexed = $needsCopy ? 0 : (int) $existing['face_indexed'];
            $updateStatement->execute([
                basename($realSource),
                $mime,
                $relativePath,
                $fileSize,
                $modifiedAt,
                $faceIndexed,
                $photoId,
            ]);
        } else {
            $insertStatement->execute([
                $eventId,
                $sourceId,
                basename($realSource),
                $mime,
                $relativePath,
                $fileSize,
                $modifiedAt,
            ]);
            $photoId = (int) db()->lastInsertId();
            if ($photoId <= 0) {
                $findIdStatement->execute([$eventId, $sourceId]);
                $photoId = (int) $findIdStatement->fetchColumn();
            }
        }

        if ($needsCopy) {
            $result['updated']++;
        }

        $needsIndex = $needsCopy || !$existing || (int) ($existing['face_indexed'] ?? 0) === 0;
        if ($indexFaces && $needsIndex) {
            try {
                $indexResult = face_index_photo($eventId, $photoId, $absolutePath);
                $faces = (int) ($indexResult['faces'] ?? 0);
                $indexStatusStatement->execute([$faces > 0 ? 1 : 2, $photoId]);
                if ($faces > 0) {
                    $result['indexed']++;
                } else {
                    $result['failed'][] = 'ไม่พบใบหน้า: ' . basename($realSource);
                }
            } catch (Throwable $exception) {
                $message = $exception->getMessage();
                $noFace = str_contains($message, 'ไม่พบใบหน้า');
                $indexStatusStatement->execute([$noFace ? 2 : 0, $photoId]);
                $result['failed'][] = ($noFace ? 'ไม่พบใบหน้า: ' : 'ทำดัชนีไม่สำเร็จ: ')
                    . basename($realSource)
                    . ($noFace ? '' : ' — ' . $message);
            }
        } elseif (!$needsCopy) {
            $result['unchanged']++;
        }
    }

    if ($seenSourceIds) {
        $placeholders = implode(',', array_fill(0, count($seenSourceIds), '?'));
        $parameters = array_merge([$eventId], $seenSourceIds);
        $hideStatement = db()->prepare(
            "UPDATE photos SET is_visible = 0, updated_at = NOW()
             WHERE event_id = ? AND is_visible = 1 AND drive_file_id NOT IN ({$placeholders})"
        );
        $hideStatement->execute($parameters);
        $result['hidden'] = $hideStatement->rowCount();
    } else {
        $hideStatement = db()->prepare(
            'UPDATE photos SET is_visible = 0, updated_at = NOW() WHERE event_id = ? AND is_visible = 1'
        );
        $hideStatement->execute([$eventId]);
        $result['hidden'] = $hideStatement->rowCount();
    }

    return $result;
}

function auto_sync_status_path(): string
{
    return STORAGE_PATH . '/auto-sync-status.json';
}

/**
 * @return array<string,mixed>|null
 */
function read_auto_sync_status(): ?array
{
    $path = auto_sync_status_path();
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function auto_sync_is_running(?array $status = null): bool
{
    $status ??= read_auto_sync_status();
    $lastCheck = isset($status['last_check_unix']) ? (int) $status['last_check_unix'] : 0;
    $interval = isset($status['interval_seconds']) ? max(5, (int) $status['interval_seconds']) : 15;
    return $lastCheck > 0 && (time() - $lastCheck) <= max(60, $interval * 4);
}
