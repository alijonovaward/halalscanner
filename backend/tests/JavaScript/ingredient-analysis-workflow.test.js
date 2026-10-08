import assert from 'node:assert/strict';
import test from 'node:test';

import {
    analyzeIngredients,
    describeRecognizedProduct,
    processIngredientImage,
    recognizeIngredientImage,
} from '../../resources/js/ingredient-analysis-workflow.js';

function jsonResponse(body, { status = 200 } = {}) {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    };
}

test('describes whether the product type came from label text or visual inference', () => {
    assert.equal(describeRecognizedProduct({
        productName: 'Северная',
        productType: 'водка',
        productTypeBasis: 'label',
    }), 'Определено по этикетке: Северная — водка.');

    assert.equal(describeRecognizedProduct({
        productName: '',
        productType: 'водка',
        productTypeBasis: 'visual',
    }), 'Предположительно по виду: водка.');
});

test('recognizes the whole label and product identity as multipart data', async () => {
    const image = new File(['label'], 'label.jpg', { type: 'image/jpeg' });
    let request;

    const recognition = await recognizeIngredientImage({
        image,
        recognitionUrl: '/api/ingredient-image-recognitions',
        fetchImpl: async (url, options) => {
            request = { url, options };

            return jsonResponse({
                data: {
                    label_text: 'ВОДКА\nСеверная\n40%',
                    product_name: 'Северная',
                    product_type: 'водка',
                    product_type_basis: 'label',
                    ingredients_text: 'Сахар, желатин',
                    quality: 'complete',
                    detected_language: 'ru',
                },
            });
        },
    });

    assert.equal(request.url, '/api/ingredient-image-recognitions');
    assert.equal(request.options.method, 'POST');
    assert.equal(request.options.body.get('image'), image);
    assert.deepEqual(recognition, {
        labelText: 'ВОДКА\nСеверная\n40%',
        ingredientsText: 'Сахар, желатин',
        productName: 'Северная',
        productType: 'водка',
        productTypeBasis: 'label',
        sourceQuality: 'complete',
        detectedLanguage: 'ru',
    });
});

test('analyzes full label text with recognized product and ingredient context', async () => {
    let request;

    const result = await analyzeIngredients({
        labelText: 'Конфеты\nСостав: сахар, желатин рыбный',
        ingredientsText: 'Сахар, желатин рыбный',
        sourceQuality: 'complete',
        barcode: '4780042470202',
        productName: 'Конфеты',
        productType: 'конфеты',
        productTypeBasis: 'label',
        analysisUrl: '/api/ingredient-analyses',
        fetchImpl: async (url, options) => {
            request = { url, options };

            return jsonResponse({ data: { screening: { status: 'needs_review' } } });
        },
    });

    assert.equal(request.url, '/api/ingredient-analyses');
    assert.deepEqual(JSON.parse(request.options.body), {
        label_text: 'Конфеты\nСостав: сахар, желатин рыбный',
        ingredients_text: 'Сахар, желатин рыбный',
        barcode: '4780042470202',
        product_name: 'Конфеты',
        product_type: 'конфеты',
        product_type_basis: 'label',
        source_quality: 'complete',
    });
    assert.deepEqual(result, { screening: { status: 'needs_review' } });
});

test('publishes the recognized label before analyzing all product context', async () => {
    const events = [];

    const result = await processIngredientImage({
        image: new File(['label'], 'label.jpg', { type: 'image/jpeg' }),
        recognize: async () => ({
            labelText: 'Лимонад\nСостав: сахар, ароматизатор',
            ingredientsText: 'Сахар, ароматизатор',
            productName: 'Лимонад',
            productType: 'безалкогольный напиток',
            productTypeBasis: 'label',
            sourceQuality: 'partial',
            detectedLanguage: 'ru',
        }),
        onRecognized: (recognition) => {
            events.push(`recognized:${recognition.labelText}`);
        },
        analyze: async (input) => {
            events.push(
                `analyzed:${input.labelText}:${input.productType}:${input.productTypeBasis}:${input.sourceQuality}`,
            );

            return { screening: { status: 'insufficient_text' } };
        },
    });

    assert.deepEqual(events, [
        'recognized:Лимонад\nСостав: сахар, ароматизатор',
        'analyzed:Лимонад\nСостав: сахар, ароматизатор:безалкогольный напиток:label:partial',
    ]);
    assert.deepEqual(result.analysis, { screening: { status: 'insufficient_text' } });
});

test('keeps recognized text available when automatic analysis fails', async () => {
    let recognizedText = '';

    await assert.rejects(
        processIngredientImage({
            image: new File(['label'], 'label.jpg', { type: 'image/jpeg' }),
            recognize: async () => ({
                ingredientsText: 'Какао, молоко',
                sourceQuality: 'complete',
                detectedLanguage: 'ru',
            }),
            onRecognized: (recognition) => {
                recognizedText = recognition.ingredientsText;
            },
            analyze: async () => {
                throw new Error('AI недоступен');
            },
        }),
        /AI недоступен/,
    );

    assert.equal(recognizedText, 'Какао, молоко');
});
