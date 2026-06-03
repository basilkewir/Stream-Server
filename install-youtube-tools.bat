@echo off
echo Installing YouTube metadata extraction tools for Windows...

REM Check if yt-dlp is available
yt-dlp --version >nul 2>&1
if %ERRORLEVEL% == 0 (
    echo ✓ yt-dlp is already installed
    yt-dlp --version
    goto :test
)

REM Try to install via pip
python -m pip --version >nul 2>&1
if %ERRORLEVEL% == 0 (
    echo Installing yt-dlp via pip...
    python -m pip install yt-dlp
    goto :test
)

REM Try pip3
pip3 --version >nul 2>&1
if %ERRORLEVEL% == 0 (
    echo Installing yt-dlp via pip3...
    pip3 install yt-dlp
    goto :test
)

REM Check for chocolatey
choco --version >nul 2>&1
if %ERRORLEVEL% == 0 (
    echo Installing yt-dlp via Chocolatey...
    choco install yt-dlp
    goto :test
)

REM Manual installation instructions
echo.
echo ❌ Could not install yt-dlp automatically.
echo.
echo Please install Python and yt-dlp manually:
echo   1. Install Python from: https://python.org/downloads/
echo   2. Open Command Prompt and run: pip install yt-dlp
echo.
echo Alternative: Install using Chocolatey
echo   1. Install Chocolatey: https://chocolatey.org/install
echo   2. Run: choco install yt-dlp
echo.
echo Or download yt-dlp directly:
echo   https://github.com/yt-dlp/yt-dlp/releases
pause
exit /b 1

:test
REM Verify installation
yt-dlp --version >nul 2>&1
if %ERRORLEVEL% == 0 (
    echo ✓ yt-dlp installation successful!
    echo Testing YouTube metadata extraction...
    
    REM Test with a safe, short video
    yt-dlp --no-playlist --dump-json "https://www.youtube.com/watch?v=dQw4w9WgXcQ" >nul 2>&1
    if %ERRORLEVEL% == 0 (
        echo ✓ YouTube metadata extraction test passed!
    ) else (
        echo ⚠️ yt-dlp installed but metadata extraction test failed
        echo This might be due to network issues or YouTube blocking
    )
) else (
    echo ❌ Installation failed. Please install yt-dlp manually.
    pause
    exit /b 1
)

echo.
echo Installation complete! Your YouTube videos should now show proper duration and metadata.
echo.
echo Alternative: YouTube Data API v3
echo   Instead of yt-dlp, you can use Google's YouTube Data API:
echo   1. Get an API key at https://console.cloud.google.com/apis/credentials
echo   2. Add to your .env file: YOUTUBE_API_KEY=your-key-here
echo   3. No local tools needed - works via simple HTTP requests
echo.
echo Would you like to set a YouTube API key now? (y/n)
set /p SET_KEY=""
if /i "%SET_KEY%"=="y" (
    echo Enter your YouTube Data API v3 key:
    set /p API_KEY=""
    if exist .env (
        powershell -Command "(Get-Content .env) -replace 'YOUTUBE_API_KEY=.*', 'YOUTUBE_API_KEY=%API_KEY%' | Set-Content .env"
        echo Key saved to .env file.
    ) else (
        echo YOUTUBE_API_KEY=%API_KEY%> .env_api_key.txt
        echo Key saved to .env_api_key.txt - copy this line into your .env file.
    )
)
echo.
pause