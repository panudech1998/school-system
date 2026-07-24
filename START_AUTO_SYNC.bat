@echo off
chcp 65001 >nul
setlocal EnableExtensions
cd /d "%~dp0"
title SWK_Phonto Auto Sync

set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" (
  for /f "delims=" %%I in ('where php 2^>nul') do if not defined PHP_FROM_PATH set "PHP_FROM_PATH=%%I"
  if defined PHP_FROM_PATH set "PHP_EXE=%PHP_FROM_PATH%"
)

if not exist "%PHP_EXE%" (
  echo ไม่พบ PHP กรุณาตรวจสอบว่าติดตั้ง XAMPP ที่ C:\xampp
  pause
  exit /b 1
)

echo ============================================
echo   SWK_Phonto - Auto Sync
echo ============================================
echo.
echo ระบบจะตรวจโฟลเดอร์ทุก 15 วินาที
echo เมื่อพบรูปใหม่หรือรูปที่แก้ไข จะซิงก์และทำดัชนีอัตโนมัติ
echo ห้ามปิดหน้าต่างนี้ขณะต้องการใช้ Auto Sync
echo กด Ctrl+C เพื่อหยุด
echo.

"%PHP_EXE%" "%~dp0scripts\auto-sync.php" 15

echo.
echo Auto Sync หยุดทำงานแล้ว
pause
