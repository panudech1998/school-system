@echo off
chcp 65001 >nul
setlocal

set "SHORTCUT=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\SWK_Phonto.lnk"

if exist "%SHORTCUT%" del /f /q "%SHORTCUT%"

if exist "%SHORTCUT%" (
  echo ลบรายการเปิดอัตโนมัติไม่สำเร็จ
) else (
  echo ปิดการเปิด SWK_Phonto อัตโนมัติแล้ว
)

pause
