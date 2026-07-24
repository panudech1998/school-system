@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"

set "TARGET=%~dp0START_SWK_PHONTO.bat"
set "SHORTCUT=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\SWK_Phonto.lnk"

powershell -NoProfile -ExecutionPolicy Bypass -Command "$shell = New-Object -ComObject WScript.Shell; $shortcut = $shell.CreateShortcut('%SHORTCUT%'); $shortcut.TargetPath = '%TARGET%'; $shortcut.WorkingDirectory = '%~dp0'; $shortcut.Save()"

if exist "%SHORTCUT%" (
  echo ติดตั้งสำเร็จ SWK_Phonto จะเปิด Face Service และ Auto Sync เมื่อเข้าสู่ Windows
) else (
  echo สร้างรายการเปิดอัตโนมัติไม่สำเร็จ
)

pause
