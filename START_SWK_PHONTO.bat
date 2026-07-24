@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title SWK_Phonto Launcher

if not exist "%~dp0START_FACE_SERVICE.bat" (
  echo Missing START_FACE_SERVICE.bat
  pause
  exit /b 1
)

if not exist "%~dp0START_AUTO_SYNC.bat" (
  echo Missing START_AUTO_SYNC.bat
  pause
  exit /b 1
)

echo Starting Face Service...
start "SWK_Phonto Face Service" cmd /k call "%~dp0START_FACE_SERVICE.bat"

echo Waiting for Face Service...
timeout /t 10 /nobreak >nul

echo Starting Auto Sync...
start "SWK_Phonto Auto Sync" cmd /k call "%~dp0START_AUTO_SYNC.bat"

timeout /t 2 /nobreak >nul
start "" "http://localhost/SWK_Phonto/admin/"

echo SWK_Phonto started.
exit /b 0
