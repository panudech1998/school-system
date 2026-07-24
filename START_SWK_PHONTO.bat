@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
title เริ่มระบบ SWK_Phonto

echo กำลังเปิด Face Service...
start "SWK_Phonto Face Service" cmd /k call "%~dp0START_FACE_SERVICE.bat"

echo รอ Face Service เริ่มทำงาน...
timeout /t 10 /nobreak >nul

echo กำลังเปิด Auto Sync...
start "SWK_Phonto Auto Sync" cmd /k call "%~dp0START_AUTO_SYNC.bat"

timeout /t 2 /nobreak >nul
start "" "http://localhost/SWK_Phonto/admin/"

echo เปิดระบบเรียบร้อยแล้ว
exit /b 0
