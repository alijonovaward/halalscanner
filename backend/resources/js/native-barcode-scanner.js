import { Capacitor } from '@capacitor/core';

import { normalizeProductBarcode } from './barcode-scanner.js';

// Values are part of the pinned @capacitor/barcode-scanner 3.1.2 contract. Keeping
// them here lets the web entry point lazy-load the Android-only implementation.
const nativeOptions = {
    allFormats: 17,
    backCamera: 1,
    adaptiveOrientation: 3,
    scanningLibrary: 'mlkit',
};
const nativeCancellationCode = 'OS-PLUG-BARC-0006';

const supportedNativeFormats = new Set([
    9, // EAN-13
    10, // EAN-8
    14, // UPC-A
    15, // UPC-E
    5, // Code 128
    8, // ITF
]);

function isNativeScannerCancellation(error) {
    if (typeof error === 'object' && error !== null && error.code === nativeCancellationCode) {
        return true;
    }

    const message = error instanceof Error ? error.message : String(error ?? '');

    return message.includes(nativeCancellationCode);
}

export async function scanNativeProductBarcode({
    capacitor = Capacitor,
    scanner,
} = {}) {
    const isAvailable = capacitor.isNativePlatform()
        && capacitor.getPlatform() === 'android'
        && capacitor.isPluginAvailable('CapacitorBarcodeScanner');

    if (!isAvailable) {
        return { status: 'unavailable' };
    }

    let result;

    try {
        const nativeScanner = scanner
            ?? (await import('@capacitor/barcode-scanner')).CapacitorBarcodeScanner;

        result = await nativeScanner.scanBarcode({
            hint: nativeOptions.allFormats,
            scanInstructions: 'Наведите камеру на товарный штрихкод',
            scanButton: false,
            scanText: 'Сканировать',
            cameraDirection: nativeOptions.backCamera,
            scanOrientation: nativeOptions.adaptiveOrientation,
            cancelButtonAccessibilityLabel: 'Отменить сканирование',
            torchButtonOnAccessibilityLabel: 'Выключить фонарик',
            torchButtonOffAccessibilityLabel: 'Включить фонарик',
            android: {
                scanningLibrary: nativeOptions.scanningLibrary,
            },
        });
    } catch (error) {
        if (isNativeScannerCancellation(error)) {
            return { status: 'cancelled' };
        }

        throw new Error(
            'Не удалось открыть сканер. Проверьте разрешение камеры или введите штрихкод вручную.',
        );
    }

    if (!String(result.ScanResult ?? '').trim()) {
        return { status: 'cancelled' };
    }

    const barcode = normalizeProductBarcode(result.ScanResult);

    if (!supportedNativeFormats.has(result.format) || !barcode) {
        throw new Error(
            'Не найден поддерживаемый товарный штрихкод. Попробуйте снова или введите его вручную.',
        );
    }

    return { status: 'scanned', barcode };
}
