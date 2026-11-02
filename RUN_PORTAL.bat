@echo off
setlocal EnableExtensions
title Student Doubt Portal - Dependency Checker
color 0B

echo.
echo  ==========================================
echo       STUDENT DOUBT PORTAL - SETUP
echo  ==========================================
echo.
echo Checking required software...

set "PROJECT=%~dp0"
set "XAMPP="

REM 1. Check common XAMPP installation locations.
for %%P in (
  "C:\xampp"
  "D:\xampp"
  "E:\xampp"
  "C:\Program Files\xampp"
  "C:\Program Files (x86)\xampp"
) do (
  if exist "%%~P\apache\bin\httpd.exe" set "XAMPP=%%~P"
)

REM 2. Check an optional custom path saved in the environment.
if not defined XAMPP if defined XAMPP_HOME if exist "%XAMPP_HOME%\apache\bin\httpd.exe" set "XAMPP=%XAMPP_HOME%"

REM 3. Ask for a custom path if XAMPP is installed somewhere unusual.
if not defined XAMPP (
  echo XAMPP was not found in common locations.
  echo.
  set /p "CUSTOM=If XAMPP is installed elsewhere, enter its folder path; otherwise press Enter to download it: "
  if not "%CUSTOM%"=="" (
    if exist "%CUSTOM%\apache\bin\httpd.exe" set "XAMPP=%CUSTOM%"
  )
)

REM 4. If still missing, download the Windows XAMPP installer from SourceForge.
if not defined XAMPP (
  echo.
  echo XAMPP is required but was not found.
  echo The free Windows installer will now be downloaded from the XAMPP SourceForge project.
  echo This requires an internet connection.
  echo.
  set "INSTALLER=%TEMP%\xampp-windows-installer.exe"
  powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$ErrorActionPreference='Stop'; $u='https://sourceforge.net/projects/xampp/files/latest/download'; $o=Join-Path $env:TEMP 'xampp-windows-installer.exe'; try { Invoke-WebRequest -Uri $u -OutFile $o -MaximumRedirection 10 -UseBasicParsing; $f=Get-Item $o; if ($f.Length -lt 50000000) { throw 'Downloaded file is unexpectedly small; it may not be the installer.' }; Write-Host ('Downloaded installer: ' + $f.FullName + ' (' + [math]::Round($f.Length/1MB) + ' MB)') } catch { Write-Host ('Download failed: ' + $_.Exception.Message); exit 1 }"
  if errorlevel 1 (
    echo.
    echo Automatic download failed.
    echo Please download XAMPP manually from https://www.apachefriends.org/
    echo Then rerun this BAT file.
    pause
    exit /b 1
  )
  echo.
  echo Opening the XAMPP installer.
  echo Complete the installation wizard. The recommended folder is C:\xampp.
  echo After installation, run RUN_PORTAL.bat again.
  start "" "%TEMP%\xampp-windows-installer.exe"
  echo.
  echo The installer has been opened. This launcher will now exit.
  pause
  exit /b 0
)

echo XAMPP found at: %XAMPP%

REM 5. Check that the portal files are in XAMPP's htdocs directory.
set "TARGET=%XAMPP%\htdocs\Student_Doubt_Portal"
if /I not "%PROJECT:~0,-1%"=="%TARGET%" (
  if not exist "%TARGET%\index.php" (
    echo.
    echo Copying portal files into XAMPP htdocs...
    if not exist "%TARGET%" mkdir "%TARGET%"
    copy /Y "%PROJECT%index.php" "%TARGET%\index.php" >nul
    copy /Y "%PROJECT%README.txt" "%TARGET%\README.txt" >nul
    if errorlevel 1 (
      echo Could not copy the files. Please copy the Student_Doubt_Portal folder manually to:
      echo %XAMPP%\htdocs\
      pause
      exit /b 1
    )
  )
)

REM 6. Start Apache and MySQL using the installed XAMPP scripts.
echo.
echo Starting Apache and MySQL...
if exist "%XAMPP%\apache_start.bat" (
  start "XAMPP Apache" /min cmd /c ""%XAMPP%\apache_start.bat""
) else (
  echo Apache start script was not found. You may need to start Apache in XAMPP Control Panel.
)
if exist "%XAMPP%\mysql_start.bat" (
  start "XAMPP MySQL" /min cmd /c ""%XAMPP%\mysql_start.bat""
) else (
  echo MySQL start script was not found. You may need to start MySQL in XAMPP Control Panel.
)

echo.
echo Waiting a few seconds for the services to start...
timeout /t 7 /nobreak >nul
start "" "http://localhost/Student_Doubt_Portal/"
echo.
echo Portal opened in your browser:
echo http://localhost/Student_Doubt_Portal/
echo.
echo If it does not load, open XAMPP Control Panel and click Start for Apache and MySQL.
echo Keep those services running while using the portal.
pause
