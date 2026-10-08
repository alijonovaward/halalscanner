import assert from 'node:assert/strict';
import test from 'node:test';

import {
    barcodeCaptureMode,
    normalizeProductBarcode,
} from '../../resources/js/barcode-scanner.js';

test('uses the live scanner only when camera streaming is available securely', () => {
    assert.equal(barcodeCaptureMode({
        isSecureContext: true,
        hasMediaDevices: true,
    }), 'live');

    assert.equal(barcodeCaptureMode({
        isSecureContext: false,
        hasMediaDevices: true,
    }), 'photo');

    assert.equal(barcodeCaptureMode({
        isSecureContext: true,
        hasMediaDevices: false,
    }), 'photo');
});

test('normalizes a detected retail barcode and rejects unsupported values', () => {
    assert.equal(normalizeProductBarcode(' 4780042470202 '), '4780042470202');
    assert.equal(normalizeProductBarcode('1234 5678'), '12345678');
    assert.equal(normalizeProductBarcode('ABC-123'), '');
    assert.equal(normalizeProductBarcode('1234567'), '');
    assert.equal(normalizeProductBarcode('123456789012345'), '');
});
