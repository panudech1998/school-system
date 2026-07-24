@echo off
chcp 65001 >nul
setlocal EnableExtensions
cd /d "%~dp0"
title SWK_Phonto Face Service

echo ============================================
echo   SWK_Phonto - ระบบค้นหารูปด้วยใบหน้า
echo ============================================
echo.

echo [1/4] ตรวจสอบ Python 3.11...
where py >nul 2>nul
if errorlevel 1 (
  echo.
  echo ไม่พบ Python กรุณาติดตั้ง Python 3.11 และเลือก Add Python to PATH
  echo จากนั้นเปิดไฟล์นี้ใหม่อีกครั้ง
  echo.
  pause
  exit /b 1
)

py -3.11 --version >nul 2>nul
if errorlevel 1 (
  echo.
  echo ไม่พบ Python 3.11 กรุณาติดตั้ง Python 3.11 ก่อนใช้งาน
  echo.
  pause
  exit /b 1
)

if not exist ".venv\Scripts\python.exe" (
  echo [2/4] สร้างสภาพแวดล้อม Python ครั้งแรก...
  py -3.11 -m venv .venv
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

:failed
echo.
echo ติดตั้งหรือเปิด Face Service ไม่สำเร็จ กรุณาตรวจสอบข้อความด้านบน
pause
exit /b 1
