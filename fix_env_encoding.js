const fs = require('fs');
const path = require('path');

const envFile = path.join('C:', 'esprit', '3A', 'Pi Dev', 'Esprit-PIDEV-3A8-2526-voyageLoisir', '.env');

// 1. Check for NUL bytes before
const bytesBefore = fs.readFileSync(envFile);
const nulPresentBefore = bytesBefore.includes(0);

// 2. Determine if conversion is needed and perform it
let conversionPerformed = false;
let content;

if (nulPresentBefore) {
    try {
        // Decode from UTF-16 LE (remove BOM if present)
        content = bytesBefore.toString('utf-16le');
    } catch (e) {
        try {
            content = bytesBefore.toString('utf-16');
        } catch (e2) {
            content = bytesBefore.toString('latin1');
        }
    }
    
    // Write back as UTF-8 without BOM
    fs.writeFileSync(envFile, content, 'utf-8');
    conversionPerformed = true;
}

// 3. Check for NUL bytes after
const bytesAfter = fs.readFileSync(envFile);
const nulPresentAfter = bytesAfter.includes(0);

// 4. Check for key presence
const contentAfter = fs.readFileSync(envFile, 'utf-8');
const keysToCheck = ['APP_ENV', 'APP_SECRET', 'DATABASE_URL', 'MESSENGER_TRANSPORT_DSN'];
const keysPresent = {};

for (const key of keysToCheck) {
    keysPresent[key] = contentAfter.split('\n').some(line => {
        const parts = line.split('=');
        return parts.length > 0 && parts[0].trim() === key;
    });
}

const result = {
    nul_present_before: nulPresentBefore,
    conversion_performed: conversionPerformed,
    nul_present_after: nulPresentAfter,
    keys: keysPresent
};

console.log(JSON.stringify(result, null, 2));
