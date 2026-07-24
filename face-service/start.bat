@echo off
setlocal
cd /d %~dp0

where py >nul 2>nul
if errorlevel 1 (
  echo ไม่พบ Python กรุณาติดตั้ง Python 3.10 หรือ 3.11 ก่อน
  pause
  exit /b 1
)

if not exist .venv (
  py -m venv .venv
)
call .venv\Scripts\activate.bat
python -m pip install --upgrade pip
pip install -r requirements.txt
python app.py
pause
