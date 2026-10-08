import assert from 'node:assert/strict';
import test from 'node:test';

import {
    calculateTargetDimensions,
    prepareImageForUpload,
} from '../../resources/js/image-preparation.js';

const mebibyte = 1024 * 1024;

test('keeps an image that already fits the upload limit', async () => {
    const file = new File(['small image'], 'label.jpg', { type: 'image/jpeg' });
    let decoded = false;

    const result = await prepareImageForUpload(file, {
        decodeImage: async () => {
            decoded = true;
        },
    });

    assert.equal(result.file, file);
    assert.equal(result.wasCompressed, false);
    assert.equal(decoded, false);
});

test('resizes a large iPhone photo while preserving its aspect ratio', async () => {
    const file = { name: 'IMG_1234.JPG', size: 12 * mebibyte, type: 'image/jpeg' };
    const calls = [];
    let closed = false;

    const result = await prepareImageForUpload(file, {
        decodeImage: async () => ({
            width: 4032,
            height: 3024,
            close: () => {
                closed = true;
            },
        }),
        encodeJpeg: async (image, options) => {
            calls.push({ image, options });

            return new Blob([new Uint8Array(4 * mebibyte)], { type: 'image/jpeg' });
        },
    });

    assert.equal(result.wasCompressed, true);
    assert.equal(result.file.name, 'IMG_1234-prepared.jpg');
    assert.equal(result.file.type, 'image/jpeg');
    assert.equal(result.file.size, 4 * mebibyte);
    assert.deepEqual(calls[0].options, { width: 2560, height: 1920, quality: 0.86 });
    assert.equal(closed, true);
});

test('uses a smaller fallback when the first encoding remains too large', async () => {
    const file = { name: 'label.png', size: 10 * mebibyte, type: 'image/png' };
    const encodedSizes = [7 * mebibyte, 5 * mebibyte];
    const calls = [];

    const result = await prepareImageForUpload(file, {
        decodeImage: async () => ({ width: 4000, height: 3000 }),
        encodeJpeg: async (image, options) => {
            calls.push(options);

            return new Blob([new Uint8Array(encodedSizes.shift())], { type: 'image/jpeg' });
        },
    });

    assert.equal(result.file.size, 5 * mebibyte);
    assert.deepEqual(calls, [
        { width: 2560, height: 1920, quality: 0.86 },
        { width: 2200, height: 1650, quality: 0.8 },
    ]);
});

test('reports when an image cannot be reduced to a safe upload size', async () => {
    const file = { name: 'huge.jpg', size: 20 * mebibyte, type: 'image/jpeg' };

    await assert.rejects(
        prepareImageForUpload(file, {
            decodeImage: async () => ({ width: 8000, height: 6000 }),
            encodeJpeg: async () => new Blob(
                [new Uint8Array(7 * mebibyte)],
                { type: 'image/jpeg' },
            ),
        }),
        /Не удалось уменьшить фотографию/,
    );
});

test('never enlarges a source image', () => {
    assert.deepEqual(calculateTargetDimensions(1600, 1200, 2560), {
        width: 1600,
        height: 1200,
    });
});
