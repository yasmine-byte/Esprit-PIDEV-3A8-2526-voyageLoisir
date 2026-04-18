@echo off
setlocal enabledelayedexpansion

cd /d "C:\esprit\3A\Pi Dev\Esprit-PIDEV-3A8-2526-voyageLoisir"

echo ======================================================================
echo PHP FILES SYNTAX CHECK
echo ======================================================================

set php_pass=0
set php_fail=0
set php_skip=0

for %%F in (
    "src/Controller/BlogController.php"
    "src/Service/MlClassifierService.php"
    "src/Service/MlDatasetExporterService.php"
    "src/Command/MlExportArticlesCommand.php"
    "src/Command/MlRetrainCommand.php"
    "src/Entity/MlPrediction.php"
    "src/Repository/MlPredictionRepository.php"
    "src/Form/BlogType.php"
    "src/Entity/Blog.php"
    "migrations/Version20260418153000.php"
) do (
    if exist "%%F" (
        php -l "%%F" >nul 2>&1
        if !errorlevel! equ 0 (
            echo ✓ PASS: %%F
            set /a php_pass=!php_pass!+1
        ) else (
            echo ✗ FAIL: %%F
            php -l "%%F"
            set /a php_fail=!php_fail!+1
        )
    ) else (
        echo ⊘ SKIP: %%F (file not found)
        set /a php_skip=!php_skip!+1
    )
)

echo.
echo ======================================================================
echo PYTHON FILES SYNTAX CHECK
echo ======================================================================

set python_pass=0
set python_fail=0
set python_skip=0

for %%F in (
    "ml-service/app.py"
    "ml-service/train.py"
) do (
    if exist "%%F" (
        python -m py_compile "%%F" >nul 2>&1
        if !errorlevel! equ 0 (
            echo ✓ PASS: %%F
            set /a python_pass=!python_pass!+1
        ) else (
            echo ✗ FAIL: %%F
            python -m py_compile "%%F"
            set /a python_fail=!python_fail!+1
        )
    ) else (
        echo ⊘ SKIP: %%F (file not found)
        set /a python_skip=!python_skip!+1
    )
)

echo.
echo ======================================================================
echo SUMMARY
echo ======================================================================
set /a php_total=!php_pass!+!php_fail!+!php_skip!
set /a python_total=!python_pass!+!python_fail!+!python_skip!
set /a total_pass=!php_pass!+!python_pass!
set /a total_fail=!php_fail!+!python_fail!
set /a total_skip=!php_skip!+!python_skip!
set /a grand_total=!php_total!+!python_total!

echo PHP Files:    PASS: !php_pass! ^| FAIL: !php_fail! ^| SKIP: !php_skip! (Total: !php_total!)
echo Python Files: PASS: !python_pass! ^| FAIL: !python_fail! ^| SKIP: !python_skip! (Total: !python_total!)
echo ======================================================================
echo Overall:      PASS: !total_pass! ^| FAIL: !total_fail! ^| SKIP: !total_skip! (Total: !grand_total!)

endlocal
