@echo off
setlocal

net session >nul 2>&1
if not "%ERRORLEVEL%"=="0" (
    echo Requesting administrator permission to start IIS...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

echo Starting IIS services...
%SystemRoot%\System32\iisreset.exe /start
set "RESULT=%ERRORLEVEL%"

echo.
if "%RESULT%"=="0" (
    echo IIS start command completed successfully.
    echo You can now open the Root Crops Research Portal.
) else (
    echo IIS start command failed with error code %RESULT%.
    echo If IIS is already running, this may be safe to ignore.
    echo Otherwise, contact the system administrator.
)

echo.
pause
exit /b %RESULT%
