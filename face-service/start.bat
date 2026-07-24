@echo off
setlocal
cd /d %~dp0
where py >nul 2>nul
if errorlevel 1 (
  echo Python not found. Install Python 3.10 or 3.11.
  pause
  exit /b 1
)
if not exist .venv py -3.11 -m venv .venv
call .venv\Scripts\activate.bat
python -m pip install --upgrade pip
pip install -r requirements.txt
set FACE_SERVICE_TOKEN=change-this-face-service-token
python app.py
pause
