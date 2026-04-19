#!/usr/bin/env python3
import json
import os

env_file = r'C:\esprit\3A\Pi Dev\Esprit-PIDEV-3A8-2526-voyageLoisir\.env'

# 1. Check for NUL bytes before
with open(env_file, 'rb') as f:
    bytes_before = f.read()
nul_present_before = b'\x00' in bytes_before

# 2. Determine if conversion is needed and perform it
conversion_performed = False
if nul_present_before:
    try:
        # Try to decode as UTF-16 LE (common encoding with BOM)
        content = bytes_before.decode('utf-16-le')
    except:
        try:
            # Try UTF-16 with BOM
            content = bytes_before.decode('utf-16')
        except:
            # Fall back to latin-1 which accepts any byte sequence
            content = bytes_before.decode('latin-1')
    
    # Write back as UTF-8 without BOM
    with open(env_file, 'w', encoding='utf-8') as f:
        f.write(content)
    conversion_performed = True

# 3. Check for NUL bytes after
with open(env_file, 'rb') as f:
    bytes_after = f.read()
nul_present_after = b'\x00' in bytes_after

# 4. Check for key presence (without printing values)
with open(env_file, 'r', encoding='utf-8') as f:
    content = f.read()

keys_to_check = ['APP_ENV', 'APP_SECRET', 'DATABASE_URL', 'MESSENGER_TRANSPORT_DSN']
keys_present = {}

for key in keys_to_check:
    # Check if key exists with = sign
    keys_present[key] = any(key in line and '=' in line for line in content.split('\n'))

result = {
    'nul_present_before': nul_present_before,
    'conversion_performed': conversion_performed,
    'nul_present_after': nul_present_after,
    'keys': keys_present
}

print(json.dumps(result, indent=2))
