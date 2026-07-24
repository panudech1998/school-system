<?php

declare(strict_types=1);

const APP_NAME = 'SWK_Phonto';
const BASE_URL = '/SWK_Phonto';

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'swk_phonto';
const DB_USER = 'root';
const DB_PASS = '';

// สร้าง OAuth Web application ที่ Google Cloud Console และเปิด Google Drive API
const GOOGLE_CLIENT_ID = '';
const GOOGLE_CLIENT_SECRET = '';
const GOOGLE_REDIRECT_URI = 'http://localhost/SWK_Phonto/admin/google-drive.php';

const FACE_SERVICE_URL = 'http://127.0.0.1:5055';
const STORAGE_PATH = __DIR__ . '/../storage';
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

const SESSION_NAME = 'swk_phonto_session';
const DEFAULT_ADMIN_EMAIL = 'admin@school.local';
const DEFAULT_ADMIN_PASSWORD = '12345678';
