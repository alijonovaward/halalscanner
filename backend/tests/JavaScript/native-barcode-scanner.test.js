import assert from 'node:assert/strict';
import test from 'node:test';

import { scanNativeProductBarcode } from '../../resources/js/native-barcode-scanner.js';

function androidCapacitor({ pluginAvailable = true } = {}) {
    return {
        getPlatform: () => 'android',
        isNativePlatform: () => true,
        isPluginAvailable: (name) => name === 'CapacitorBarcodeScanner' && pluginAvailable,
    };
}

test('reports unavailable outside an Android Capacitor runtime', async () => {
    let scannerWasCalled = false;

    const result = await scanNativeProductBarcode({
        capacitor: {
            getPlatform: () => 'web',
            isNativePlatform: () => false,
            isPluginAvailable: () => true,
        },
        scanner: {
            scanBarcode: async () => {
                scannerWasCalled = true;
                return { ScanResult: '4780042470202', format: 9 };
            },
        },
    });

    assert.deepEqual(result, { status: 'unavailable' });
    assert.equal(scannerWasCalled, false);
});

test('reports unavailable when the native scanner plugin is missing', async () => {
    const result = await scanNativeProductBarcode({
        capacitor: androidCapacitor({ pluginAvailable: false }),
        scanner: {
            scanBarcode: async () => {
                throw new Error('must not run');
            },
        },
    });

    assert.deepEqual(result, { status: 'unavailable' });
});

test('scans a normalized retail barcode with rear-camera ML Kit options', async () => {
    let receivedOptions;

    const result = await scanNativeProductBarcode({
        capacitor: androidCapacitor(),
        scanner: {
            scanBarcode: async (options) => {
                receivedOptions = options;
                return { ScanResult: ' 4780042470202 ', format: 9 };
            },
        },
    });

    assert.deepEqual(receivedOptions, {
        hint: 17,
        scanInstructions: 'Наведите камеру на товарный штрихкод',
        scanButton: false,
        scanText: 'Сканировать',
        cameraDirection: 1,
        scanOrientation: 3,
        cancelButtonAccessibilityLabel: 'Отменить сканирование',
        torchButtonOnAccessibilityLabel: 'Выключить фонарик',
        torchButtonOffAccessibilityLabel: 'Включить фонарик',
        android: { scanningLibrary: 'mlkit' },
    });
    assert.deepEqual(result, { status: 'scanned', barcode: '4780042470202' });
});

test('accepts every approved native barcode format', async () => {
    for (const format of [9, 10, 14, 15, 5, 8]) {
        const result = await scanNativeProductBarcode({
            capacitor: androidCapacitor(),
            scanner: {
                scanBarcode: async () => ({ ScanResult: '12345678', format }),
            },
        });

        assert.deepEqual(result, { status: 'scanned', barcode: '12345678' });
    }
});

test('treats an empty native result as cancellation', async () => {
    const result = await scanNativeProductBarcode({
        capacitor: androidCapacitor(),
        scanner: {
            scanBarcode: async () => ({ ScanResult: '', format: 17 }),
        },
    });

    assert.deepEqual(result, { status: 'cancelled' });
});

test('treats the Android scanner cancellation rejection as cancellation', async () => {
    const cancellationError = new Error('The scan was cancelled.');
    cancellationError.code = 'OS-PLUG-BARC-0006';

    const result = await scanNativeProductBarcode({
        capacitor: androidCapacitor(),
        scanner: {
            scanBarcode: async () => {
                throw cancellationError;
            },
        },
    });

    assert.deepEqual(result, { status: 'cancelled' });
});

test('rejects unsupported native formats and invalid product values', async () => {
    await assert.rejects(
        scanNativeProductBarcode({
            capacitor: androidCapacitor(),
            scanner: {
                scanBarcode: async () => ({ ScanResult: '12345678', format: 0 }),
            },
        }),
        /поддерживаемый товарный штрихкод/i,
    );

    await assert.rejects(
        scanNativeProductBarcode({
            capacitor: androidCapacitor(),
            scanner: {
                scanBarcode: async () => ({ ScanResult: 'ABC-12345', format: 5 }),
            },
        }),
        /поддерживаемый товарный штрихкод/i,
    );
});

test('turns native scanner failures into actionable camera guidance', async () => {
    await assert.rejects(
        scanNativeProductBarcode({
            capacitor: androidCapacitor(),
            scanner: {
                scanBarcode: async () => {
                    throw new Error('permission denied');
                },
            },
        }),
        /проверьте разрешение камеры.*введите штрихкод вручную/i,
    );
});
