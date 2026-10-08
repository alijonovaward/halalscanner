async function readJson(response) {
    try {
        return await response.json();
    } catch {
        return {};
    }
}

export function describeRecognizedProduct({
    productName = '',
    productType = '',
    productTypeBasis = 'unknown',
}) {
    if (!productType) {
        return productName ? `Распознано название: ${productName}.` : '';
    }

    const identity = [productName, productType].filter(Boolean).join(' — ');

    return productTypeBasis === 'visual'
        ? `Предположительно по виду: ${identity}.`
        : `Определено по этикетке: ${identity}.`;
}

export async function recognizeIngredientImage({
    image,
    recognitionUrl,
    fetchImpl = fetch,
}) {
    const formData = new FormData();
    formData.append('image', image);

    const response = await fetchImpl(recognitionUrl, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: formData,
    });
    const payload = await readJson(response);

    if (response.status === 422) {
        throw new Error(
            payload.errors?.image?.[0]
            ?? 'Проверьте формат и размер изображения.',
        );
    }

    if (response.status === 429) {
        throw new Error('Слишком много запросов. Подождите минуту и повторите.');
    }

    if (!response.ok) {
        throw new Error(payload.message ?? 'Не удалось распознать состав. Попробуйте ещё раз.');
    }

    const recognition = payload.data ?? {};
    const labelText = typeof recognition.label_text === 'string'
        ? recognition.label_text.trim()
        : '';
    const ingredientsText = typeof recognition.ingredients_text === 'string'
        ? recognition.ingredients_text.trim()
        : '';
    const productName = typeof recognition.product_name === 'string'
        ? recognition.product_name.trim()
        : '';
    const productType = typeof recognition.product_type === 'string'
        ? recognition.product_type.trim()
        : '';
    const productTypeBasis = ['label', 'visual', 'unknown'].includes(recognition.product_type_basis)
        ? recognition.product_type_basis
        : 'unknown';

    if (recognition.quality === 'unreadable' || (labelText.length < 3 && productType.length < 3)) {
        throw new Error('Этикетку и товар не удалось распознать. Сделайте более чёткое фото целиком.');
    }

    return {
        labelText,
        ingredientsText,
        productName,
        productType,
        productTypeBasis,
        sourceQuality: ['complete', 'partial'].includes(recognition.quality)
            ? recognition.quality
            : null,
        detectedLanguage: typeof recognition.detected_language === 'string'
            ? recognition.detected_language
            : null,
    };
}

export async function analyzeIngredients({
    labelText = '',
    ingredientsText = '',
    sourceQuality = null,
    barcode = '',
    productName = '',
    productType = '',
    productTypeBasis = '',
    analysisUrl,
    fetchImpl = fetch,
}) {
    const requestBody = {};

    if (labelText) {
        requestBody.label_text = labelText;
    }

    if (ingredientsText) {
        requestBody.ingredients_text = ingredientsText;
    }

    if (barcode) {
        requestBody.barcode = barcode;
    }

    if (productName) {
        requestBody.product_name = productName;
    }

    if (productType) {
        requestBody.product_type = productType;
    }

    if (productTypeBasis) {
        requestBody.product_type_basis = productTypeBasis;
    }

    if (sourceQuality) {
        requestBody.source_quality = sourceQuality;
    }

    const response = await fetchImpl(analysisUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody),
    });
    const payload = await readJson(response);

    if (response.status === 429) {
        throw new Error('Слишком много запросов. Подождите минуту и повторите.');
    }

    if (!response.ok) {
        throw new Error(payload.message ?? 'Попробуйте повторить анализ позже.');
    }

    return payload.data ?? {};
}

export async function processIngredientImage({
    image,
    recognize,
    analyze,
    onRecognized = () => {},
}) {
    const recognition = await recognize(image);

    onRecognized(recognition);

    const analysis = await analyze({
        labelText: recognition.labelText,
        ingredientsText: recognition.ingredientsText,
        productName: recognition.productName,
        productType: recognition.productType,
        productTypeBasis: recognition.productTypeBasis,
        sourceQuality: recognition.sourceQuality,
    });

    return { recognition, analysis };
}
