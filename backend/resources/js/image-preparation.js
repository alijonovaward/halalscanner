const mebibyte = 1024 * 1024;

export const maximumUploadSize = 8 * mebibyte;
export const targetUploadSize = 6 * mebibyte;

const compressionAttempts = [
    { maximumDimension: 2560, quality: 0.86 },
    { maximumDimension: 2200, quality: 0.8 },
    { maximumDimension: 1800, quality: 0.74 },
];

export function calculateTargetDimensions(width, height, maximumDimension) {
    const scale = Math.min(1, maximumDimension / Math.max(width, height));

    return {
        width: Math.max(1, Math.round(width * scale)),
        height: Math.max(1, Math.round(height * scale)),
    };
}

export async function prepareImageForUpload(file, {
    decodeImage = decodeBrowserImage,
    encodeJpeg = encodeImageAsJpeg,
} = {}) {
    if (file.size <= maximumUploadSize) {
        return { file, wasCompressed: false };
    }

    const image = await decodeImage(file);

    try {
        for (const attempt of compressionAttempts) {
            const dimensions = calculateTargetDimensions(
                image.width,
                image.height,
                attempt.maximumDimension,
            );
            const blob = await encodeJpeg(image, {
                ...dimensions,
                quality: attempt.quality,
            });

            if (blob.size <= targetUploadSize) {
                return {
                    file: new File(
                        [blob],
                        `${file.name.replace(/\.[^.]+$/, '')}-prepared.jpg`,
                        { type: 'image/jpeg', lastModified: Date.now() },
                    ),
                    wasCompressed: true,
                };
            }
        }
    } finally {
        image.close?.();
    }

    throw new Error(
        'Не удалось уменьшить фотографию. Сфотографируйте этикетку ближе и повторите.',
    );
}

async function decodeBrowserImage(file) {
    if (typeof createImageBitmap === 'function') {
        try {
            return await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch {
        }
    }

    return await decodeWithHtmlImage(file);
}

function decodeWithHtmlImage(file) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(file);

        image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Не удалось прочитать выбранную фотографию.'));
        };
        image.src = objectUrl;
    });
}

function encodeImageAsJpeg(image, { width, height, quality }) {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d', { alpha: false });

    if (!context) {
        throw new Error('Браузер не смог подготовить фотографию к загрузке.');
    }

    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, width, height);
    context.drawImage(image, 0, 0, width, height);

    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            canvas.width = 1;
            canvas.height = 1;

            if (blob) {
                resolve(blob);
                return;
            }

            reject(new Error('Браузер не смог сжать выбранную фотографию.'));
        }, 'image/jpeg', quality);
    });
}
