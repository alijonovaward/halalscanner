import { BrowserMultiFormatReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType } from '@zxing/library';

const supportedFormats = [
    BarcodeFormat.EAN_13,
    BarcodeFormat.EAN_8,
    BarcodeFormat.UPC_A,
    BarcodeFormat.UPC_E,
    BarcodeFormat.CODE_128,
    BarcodeFormat.ITF,
];

export function barcodeCaptureMode({ isSecureContext, hasMediaDevices }) {
    return isSecureContext && hasMediaDevices ? 'live' : 'photo';
}

export function normalizeProductBarcode(value) {
    const normalized = String(value ?? '').replace(/\s+/g, '');

    return /^\d{8,14}$/.test(normalized) ? normalized : '';
}

export function createProductBarcodeReader() {
    const hints = new Map();
    hints.set(DecodeHintType.POSSIBLE_FORMATS, supportedFormats);
    hints.set(DecodeHintType.TRY_HARDER, true);

    return new BrowserMultiFormatReader(hints, {
        delayBetweenScanAttempts: 150,
        delayBetweenScanSuccess: 500,
    });
}

export async function decodeBarcodeImage(file, {
    reader = createProductBarcodeReader(),
} = {}) {
    const imageUrl = URL.createObjectURL(file);

    try {
        const result = await reader.decodeFromImageUrl(imageUrl);
        const barcode = normalizeProductBarcode(result.getText());

        if (!barcode) {
            throw new Error('На фото не найден поддерживаемый товарный штрихкод.');
        }

        return barcode;
    } catch (error) {
        if (error instanceof Error && error.message.includes('поддерживаемый товарный')) {
            throw error;
        }

        throw new Error('Не удалось прочитать штрихкод. Снимите его крупнее и без бликов.');
    } finally {
        URL.revokeObjectURL(imageUrl);
    }
}

export async function startLiveBarcodeScanner({
    videoElement,
    onDetected,
    reader = createProductBarcodeReader(),
}) {
    let controls;

    controls = await reader.decodeFromConstraints(
        {
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
        },
        videoElement,
        (result) => {
            if (!result) {
                return;
            }

            const barcode = normalizeProductBarcode(result.getText());

            if (!barcode) {
                return;
            }

            controls?.stop();
            onDetected(barcode);
        },
    );

    return controls;
}
