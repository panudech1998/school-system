@echo off
chcp 65001 >nul
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title SWK_Phonto Face Service

echo ============================================
echo   SWK_Phonto - ระบบค้นหารูปด้วยใบหน้า
echo ============================================
echo.

set "PYTHON_CMD="
set "PYTHON_LABEL="

echo [1/4] ตรวจสอบ Python ที่รองรับ...

py -3.11 -c "import sys; raise SystemExit(0 if sys.version_info[:2] == (3,11) else 1)" >nul 2>nul
if not errorlevel 1 (
  set "PYTHON_CMD=py -3.11"
  set "PYTHON_LABEL=Python 3.11 ผ่าน py launcher"
)

if not defined PYTHON_CMD (
  py -3.10 -c "import sys; raise SystemExit(0 if sys.version_info[:2] == (3,10) else 1)" >nul 2>nul
  if not errorlevel 1 (
    set "PYTHON_CMD=py -3.10"
    set "PYTHON_LABEL=Python 3.10 ผ่าน py launcher"
  )
)

if not defined PYTHON_CMD (
  py -3.12 -c "import sys; raise SystemExit(0 if sys.version_info[:2] == (3,12) else 1)" >nul 2>nul
  if not errorlevel 1 (
    set "PYTHON_CMD=py -3.12"
    set "PYTHON_LABEL=Python 3.12 ผ่าน py launcher"
  )
)

if not defined PYTHON_CMD (
  python -c "import sys; raise SystemExit(0 if (3,10) <= sys.version_info[:2] <= (3,12) else 1)" >nul 2>nul
  if not errorlevel 1 (
    set "PYTHON_CMD=python"
    set "PYTHON_LABEL=Python จาก PATH"
  )
)

if not defined PYTHON_CMD if exist "%LocalAppData%\Programs\Python\Python311\python.exe" (
  set "PYTHON_CMD=\"%LocalAppData%\Programs\Python\Python311\python.exe\""
  set "PYTHON_LABEL=Python 3.11 ในบัญชีผู้ใช้"
)

if not defined PYTHON_CMD if exist "%LocalAppData%\Programs\Python\Python310\python.exe" (
  set "PYTHON_CMD=\"%LocalAppData%\Programs\Python\Python310\python.exe\""
  set "PYTHON_LABEL=Python 3.10 ในบัญชีผู้ใช้"
)

if not defined PYTHON_CMD if exist "%ProgramFiles%\Python311\python.exe" (
  set "PYTHON_CMD=\"%ProgramFiles%\Python311\python.exe\""
  set "PYTHON_LABEL=Python 3.11 ใน Program Files"
)

if not defined PYTHON_CMD if exist "%ProgramFiles%\Python310\python.exe" (
  set "PYTHON_CMD=\"%ProgramFiles%\Python310\python.exe\""
  set "PYTHON_LABEL=Python 3.10 ใน Program Files"
)

if not defined PYTHON_CMD (
  echo.
  echo ไม่พบ Python 3.10, 3.11 หรือ 3.12 ที่ใช้งานได้
  where winget >nul 2>nul
  if not errorlevel 1 (
    echo.
    choice /C YN /N /M "ต้องการให้ระบบติดตั้ง Python 3.11 อัตโนมัติหรือไม่? [Y/N]: "
    if errorlevel 2 goto :python_missing
    echo.
    echo กำลังติดตั้ง Python 3.11 กรุณารอสักครู่...
    winget install --exact --id Python.Python.3.11 --accept-package-agreements --accept-source-agreements
    if errorlevel 1 goto :install_failed
    echo.
    echo ติดตั้งเสร็จแล้ว กรุณาปิดหน้าต่างนี้และเปิด START_FACE_SERVICE.bat ใหม่อีกครั้ง
    pause
    exit /b 0
  )
  goto :python_missing
)

echo พบ !PYTHON_LABEL!
!PYTHON_CMD! --version

if not exist ".venv\Scripts\python.exe" (
  echo [2/4] สร้างสภาพแวดล้อม Python ครั้งแรก...
  !PYTHON_CMD! -m venv .venv
  if errorlevel 1 goto :failed
) else (
  echo [2/4] พบสภาพแวดล้อม Python แล้ว
)

call ".venv\Scripts\activate.bat"
if errorlevel 1 goto :failed

echo [3/4] ตรวจสอบและติดตั้งส่วนประกอบ...
python -m pip install --disable-pip-version-check --upgrade pip
if errorlevel 1 goto :failed
python -m pip install --disable-pip-version-check -r requirements.txt
if errorlevel 1 goto :failed

set "FACE_SERVICE_TOKEN=change-this-face-service-token"
set "PYTHONUTF8=1"

echo [4/4] กำลังเปิด Face Service ที่ http://127.0.0.1:5055
echo.
echo ห้ามปิดหน้าต่างนี้ขณะใช้งานระบบค้นหาใบหน้า
echo เมื่อเห็นข้อความ Running on http://127.0.0.1:5055 แสดงว่าพร้อมใช้งาน
echo.
start "" /b cmd /c "timeout /t 8 /nobreak >nul & start http://127.0.0.1:5055/health"
python app.py

echo.
echo Face Service หยุดทำงานแล้ว
pause
exit /b 0

:python_missing
echo.
echo กรุณาติดตั้ง Python 3.11 จาก python.org
 echo ระหว่างติดตั้งให้เลือก Add python.exe to PATH และ Install launcher for all users
pause
exit /b 1

:install_failed
echo.
echo ติดตั้ง Python อัตโนมัติไม่สำเร็จ กรุณาติดตั้ง Python 3.11 ด้วยตนเอง
pause
exit /b 1

:failed
echo.
echo ติดตั้งหรือเปิด Face Service ไม่สำเร็จ กรุณาตรวจสอบข้อความด้านบน
pause
exit /b 1
