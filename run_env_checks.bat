@echo off
cd /d "C:\esprit\3A\Pi Dev\Esprit-PIDEV-3A8-2526-voyageLoisir"

echo ===== Step 1: List env-related files at repo root =====
dir *env* 2>&1

echo.
echo ===== Step 2: Check .env file exists =====
if exist .env (
    echo .env file EXISTS
) else (
    echo .env file NOT FOUND
)

echo.
echo ===== Step 3: Full .env content =====
type .env 2>&1

echo.
echo ===== Step 4: Check for specific patterns in .env =====
echo Checking patterns:
findstr "^APP_ENV=dev" .env 2>&1
findstr "^APP_SECRET=" .env 2>&1
findstr "^DATABASE_URL=" .env 2>&1
findstr "^MESSENGER_TRANSPORT_DSN=" .env 2>&1

echo.
echo ===== Step 5a: Running php bin/console about =====
php bin/console about 2>&1

echo.
echo ===== Step 5b: Running php bin/console cache:clear =====
php bin/console cache:clear 2>&1

echo.
echo ===== End of checks =====
