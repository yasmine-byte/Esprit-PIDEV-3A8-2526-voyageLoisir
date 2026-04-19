#!/usr/bin/env python3
"""Syntax checker for PHP and Python files."""

import os
import subprocess
import sys

# Change directory
os.chdir(r'C:\esprit\3A\Pi Dev\Esprit-PIDEV-3A8-2526-voyageLoisir')

# Define files to check
php_files = [
    'src/Controller/BlogController.php',
    'src/Service/MlClassifierService.php',
    'src/Service/MlDatasetExporterService.php',
    'src/Command/MlExportArticlesCommand.php',
    'src/Command/MlRetrainCommand.php',
    'src/Entity/MlPrediction.php',
    'src/Repository/MlPredictionRepository.php',
    'src/Form/BlogType.php',
    'src/Entity/Blog.php',
    'migrations/Version20260418153000.php'
]

python_files = [
    'ml-service/app.py',
    'ml-service/train.py'
]

# Results tracking
results = {
    'php': {'pass': [], 'fail': [], 'skip': []},
    'python': {'pass': [], 'fail': [], 'skip': []}
}

print('=' * 70)
print('PHP FILES SYNTAX CHECK')
print('=' * 70)

for file in php_files:
    if os.path.exists(file):
        try:
            result = subprocess.run(['php', '-l', file], 
                                  capture_output=True, 
                                  text=True,
                                  timeout=10)
            if result.returncode == 0:
                print(f'✓ PASS: {file}')
                results['php']['pass'].append(file)
            else:
                error_msg = result.stderr if result.stderr else result.stdout
                print(f'✗ FAIL: {file}')
                print(f'  Error: {error_msg.strip()}')
                results['php']['fail'].append(file)
        except Exception as e:
            print(f'✗ FAIL: {file}')
            print(f'  Error: {str(e)}')
            results['php']['fail'].append(file)
    else:
        print(f'⊘ SKIP: {file} (file not found)')
        results['php']['skip'].append(file)

print(f'\n{"=" * 70}')
print('PYTHON FILES SYNTAX CHECK')
print('=' * 70)

for file in python_files:
    if os.path.exists(file):
        try:
            result = subprocess.run(['python', '-m', 'py_compile', file], 
                                  capture_output=True, 
                                  text=True,
                                  timeout=10)
            if result.returncode == 0:
                print(f'✓ PASS: {file}')
                results['python']['pass'].append(file)
            else:
                error_msg = result.stderr if result.stderr else result.stdout
                print(f'✗ FAIL: {file}')
                print(f'  Error: {error_msg.strip()}')
                results['python']['fail'].append(file)
        except Exception as e:
            print(f'✗ FAIL: {file}')
            print(f'  Error: {str(e)}')
            results['python']['fail'].append(file)
    else:
        print(f'⊘ SKIP: {file} (file not found)')
        results['python']['skip'].append(file)

# Summary
php_pass = len(results['php']['pass'])
php_fail = len(results['php']['fail'])
php_skip = len(results['php']['skip'])
php_total = php_pass + php_fail + php_skip

python_pass = len(results['python']['pass'])
python_fail = len(results['python']['fail'])
python_skip = len(results['python']['skip'])
python_total = python_pass + python_fail + python_skip

print(f'\n{"=" * 70}')
print('SUMMARY')
print('=' * 70)
print(f'PHP Files:    PASS: {php_pass} | FAIL: {php_fail} | SKIP: {php_skip} (Total: {php_total})')
print(f'Python Files: PASS: {python_pass} | FAIL: {python_fail} | SKIP: {python_skip} (Total: {python_total})')
print(f'{"=" * 70}')
print(f'Overall:      PASS: {php_pass + python_pass} | FAIL: {php_fail + python_fail} | SKIP: {php_skip + python_skip} (Total: {php_total + python_total})')
