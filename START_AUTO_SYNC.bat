@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title SWK_Phonto Auto Sync

set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" (
  for /f "delims=" %%I in ('where php 2^>nul') do if not defined PHP_FROM_PATH set "PHP_FROM_PATH=%%I"
  if defined PHP_FROM_PATH set "PHP_EXE=%PHP_FROM_PATH%"
)

if not exist "%PHP_EXE%" (
  echo PHP was not found.
  echo Expected location: C:\xampp\php\php.exe
  echo Install XAMPP or update PHP_EXE in START_AUTO_SYNC.bat.
  pause
  exit /b 1
)

if not exist "%~dp0scripts\auto-sync.php" (
  echo Auto Sync script was not found.
  echo Missing: %~dp0scripts\auto-sync.php
  pause
  exit /b 1
)

echo ============================================
echo   SWK_Phonto - Auto Sync
echo ============================================
echo.
echo Monitoring active event folders every 15 seconds.
echo New and modified images will be synced and indexed automatically.
echo Keep this window open. Press Ctrl+C to stop.
echo.

"%PHP_EXE%" "%~dp0scripts\auto-sync.php" 15

echo.
echo Auto Sync has stopped.
pause
