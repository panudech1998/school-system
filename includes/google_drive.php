<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function drive_connection(): ?array
{
    $row = db()->query('SELECT * FROM drive_connections WHERE id = 1 LIMIT 1')->fetch();
    return $row ?: null;
}

function drive_authorize_url(): string
{
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/drive.readonly',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => csrf_token(),
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function drive_exchange_code(string $code): array
{
    return http_form('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ]);
}

function drive_access_token(): string
{
    $connection = drive_connection();
    if (!$connection) {
        throw new RuntimeException('ยังไม่ได้เชื่อมต่อ Google Drive');
    }

    if (!empty($connection['access_token']) && strtotime((string) $connection['expires_at']) > time() + 60) {
        return (string) $connection['access_token'];
    }

    if (empty($connection['refresh_token'])) {
        throw new RuntimeException('Refresh token หาย กรุณาเชื่อมต่อ Google Drive ใหม่');
    }

    $token = http_form('https://oauth2.googleapis.com/token', [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'refresh_token' => $connection['refresh_token'],
        'grant_type' => 'refresh_token',
    ]);

    $stmt = db()->prepare('UPDATE drive_connections SET access_token = ?, expires_at = ?, updated_at = NOW() WHERE id = 1');
    $stmt->execute([
        $token['access_token'],
        date('Y-m-d H:i:s', time() + (int) ($token['expires_in'] ?? 3600)),
    ]);

    return (string) $token['access_token'];
}

function drive_save_token(array $token): void
{
    $current = drive_connection();
    $refresh = $token['refresh_token'] ?? ($current['refresh_token'] ?? null);
    $stmt = db()->prepare(
        'INSERT INTO drive_connections (id, access_token, refresh_token, expires_at, created_at, updated_at)
         VALUES (1, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), refresh_token = VALUES(refresh_token), expires_at = VALUES(expires_at), updated_at = NOW()'
    );
    $stmt->execute([
        $token['access_token'],
        $refresh,
        date('Y-m-d H:i:s', time() + (int) ($token['expires_in'] ?? 3600)),
    ]);
}

function drive_list_images(string $folderId): array
{
    $query = sprintf("'%s' in parents and trashed = false and mimeType contains 'image/'", str_replace("'", "\\'", $folderId));
    $params = [
        'q' => $query,
        'fields' => 'nextPageToken,files(id,name,mimeType,size,modifiedTime)',
        'pageSize' => 1000,
        'supportsAllDrives' => 'true',
        'includeItemsFromAllDrives' => 'true',
        'orderBy' => 'name',
    ];
    $response = drive_request('GET', 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params));
    return $response['files'] ?? [];
}

function drive_download(string $fileId, string $destination): void
{
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?alt=media&supportsAllDrives=true';
    $ch = curl_init($url);
    $fp = fopen($destination, 'wb');
    if (!$fp) {
        throw new RuntimeException('ไม่สามารถสร้างไฟล์ปลายทางได้');
    }
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . drive_access_token()],
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 180,
    ]);
    $ok = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $status >= 400) {
        @unlink($destination);
        throw new RuntimeException('ดาวน์โหลดจาก Drive ไม่สำเร็จ: ' . ($error ?: 'HTTP ' . $status));
    }
}

function drive_request(string $method, string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . drive_access_token(), 'Accept: application/json'],
        CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $status >= 400) {
        throw new RuntimeException('Google Drive API ผิดพลาด: ' . ($error ?: $body));
    }
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}

function http_form(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $status >= 400) {
        throw new RuntimeException('OAuth ผิดพลาด: ' . ($error ?: $body));
    }
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}
