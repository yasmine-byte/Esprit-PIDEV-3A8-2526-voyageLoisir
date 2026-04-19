@echo off
cd /d "C:\esprit\3A\Pi Dev\Esprit-PIDEV-3A8-2526-voyageLoisir"

echo ================================
echo === STEP 1: ENV-RELATED FILES ===
echo ================================
dir /b | findstr /i "env"

echo.
echo ====================================
echo === STEP 2: CHECKING IF .env EXISTS ===
echo ====================================
if exist ".env" (
    echo .env EXISTS - will not overwrite
) else (
    echo .env DOES NOT EXIST
)

echo.
echo ================================
echo === STEP 3: FULL .env CONTENT ===
echo ================================
type .env

echo.
echo ===================================================
echo === STEP 4: CHECKING FOR REQUIRED PATTERNS ===
echo ===================================================
echo Checking for APP_ENV=dev:
findstr /c:"APP_ENV=dev" .env
echo.
echo Checking for APP_SECRET=:
findstr /c:"APP_SECRET=" .env
echo.
echo Checking for DATABASE_URL=:
findstr /c:"DATABASE_URL=" .env
echo.
echo Checking for MESSENGER_TRANSPORT_DSN=:
findstr /c:"MESSENGER_TRANSPORT_DSN=" .env

echo.
echo ====================================
echo === STEP 5: PHP CONSOLE COMMANDS ===
echo ====================================
echo Running: php bin/console about
php bin/console about

echo.
echo Running: php bin/console cache:clear
php bin/console cache:clear

