import {
    maximumUploadSize,
    prepareImageForUpload,
} from './image-preparation.js';
import {
    barcodeCaptureMode,
    decodeBarcodeImage,
    startLiveBarcodeScanner,
} from './barcode-scanner.js';
import {
    analyzeIngredients,
    describeRecognizedProduct,
    processIngredientImage,
    recognizeIngredientImage,
} from './ingredient-analysis-workflow.js';
import { scanNativeProductBarcode } from './native-barcode-scanner.js';
import { screeningPresentation } from './screening-statuses.js';

const body = document.body;
const verdictUrlTemplate = body.dataset.verdictUrlTemplate;
const recognitionUrl = body.dataset.recognitionUrl;
const analysisUrl = body.dataset.analysisUrl;

const barcodeForm = document.querySelector('#barcode-form');
const barcodeInput = document.querySelector('#barcode');
const barcodeError = document.querySelector('#barcode-error');
const barcodeScannerButton = document.querySelector('#start-barcode-scanner');
const barcodeCameraInput = document.querySelector('#barcode-camera-input');
const barcodeScanner = document.querySelector('#barcode-scanner');
const barcodeScannerVideo = document.querySelector('#barcode-scanner-video');
const barcodeScannerMessage = document.querySelector('#barcode-scanner-message');
const closeBarcodeScannerButton = document.querySelector('#close-barcode-scanner');
const catalogResult = document.querySelector('#catalog-result');
const catalogResultTitle = document.querySelector('#catalog-result-title');
const catalogResultText = document.querySelector('#catalog-result-text');

const ingredientForm = document.querySelector('#ingredient-form');
const ingredientInput = document.querySelector('#ingredients-text');
const ingredientError = document.querySelector('#ingredient-error');
const ingredientImageInput = document.querySelector('#ingredient-image');
const ingredientCameraInput = document.querySelector('#ingredient-camera');
const imagePreview = document.querySelector('#image-preview');
const imagePreviewPicture = document.querySelector('#image-preview-picture');
const imageFileName = document.querySelector('#image-file-name');
const imageError = document.querySelector('#image-error');
const recognitionMessage = document.querySelector('#recognition-message');
const removeImageButton = document.querySelector('#remove-image');
const ingredientSubmitButton = ingredientForm?.querySelector('button[type="submit"]');
const analysisPanel = document.querySelector('#analysis-panel');
const analysisEmpty = document.querySelector('#analysis-empty');
const analysisResult = document.querySelector('#analysis-result');
const analysisStatusIcon = document.querySelector('#analysis-status-icon');
const analysisStatus = document.querySelector('#analysis-status');
const analysisSummary = document.querySelector('#analysis-summary');
const attentionList = document.querySelector('#attention-list');

let currentBarcode = '';
let currentProductName = '';
let selectedImage = null;
let previewUrl = '';
let recognizedSourceQuality = null;
let recognizedIngredientsText = '';
let recognizedProductName = '';
let recognizedProductType = '';
let recognizedProductTypeBasis = '';
let imageSelectionVersion = 0;
let barcodeScannerControls = null;
let barcodeScannerVersion = 0;

const acceptedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
const ingredientImageInputs = [ingredientImageInput, ingredientCameraInput].filter(Boolean);

const catalogStatuses = {
    halal: {
        title: 'Халяль подтверждён',
        text: 'Статус основан на проверенных данных и действующем сертификате.',
        tone: 'success',
    },
    haram: {
        title: 'Обнаружено запрещённое',
        text: 'В проверенных данных есть основание для отрицательного результата.',
        tone: 'danger',
    },
    mushbooh: {
        title: 'Требуется уточнение',
        text: 'Источники или происхождение отдельных компонентов требуют проверки.',
        tone: 'warning',
    },
    unknown: {
        title: 'Недостаточно данных',
        text: 'Добавьте фото всей этикетки для предварительной проверки.',
        tone: 'neutral',
    },
};

function setBusy(button, busy, loadingLabel) {
    const label = button.querySelector('[data-button-label]');

    if (!button.dataset.defaultLabel) {
        button.dataset.defaultLabel = label.textContent;
    }

    button.disabled = busy;
    button.classList.toggle('is-loading', busy);
    label.textContent = busy ? loadingLabel : button.dataset.defaultLabel;
}

function showError(element, message = '') {
    element.textContent = message;
    element.hidden = message === '';
}

function showRecognitionMessage(message = '') {
    recognitionMessage.textContent = message;
    recognitionMessage.hidden = message === '';
}

function revokePreviewUrl() {
    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = '';
    }
}

function clearSelectedImage({ clearMessages = true } = {}) {
    revokePreviewUrl();
    selectedImage = null;
    ingredientImageInputs.forEach((input) => {
        input.value = '';
    });
    imagePreviewPicture.removeAttribute('src');
    imageFileName.textContent = '';
    imagePreview.hidden = true;

    if (clearMessages) {
        showError(imageError);
        showRecognitionMessage();
    }
}

function showSelectedImage(file) {
    revokePreviewUrl();
    selectedImage = file;
    previewUrl = URL.createObjectURL(file);
    imagePreviewPicture.src = previewUrl;
    imageFileName.textContent = file.name;
    imagePreview.hidden = false;
}

async function readJson(response) {
    try {
        return await response.json();
    } catch {
        return {};
    }
}

function showCatalogResult(title, text, tone = 'neutral') {
    catalogResult.className = `catalog-result catalog-result--${tone}`;
    catalogResultTitle.textContent = title;
    catalogResultText.textContent = text;
    catalogResult.hidden = false;
}

function focusIngredientFallback() {
    if (window.matchMedia('(max-width: 820px)').matches) {
        ingredientForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function applyDetectedBarcode(barcode) {
    barcodeInput.value = barcode;
    barcodeInput.dispatchEvent(new Event('input', { bubbles: true }));
    barcodeForm.requestSubmit();
}

function closeBarcodeScanner({ returnFocus = false } = {}) {
    barcodeScannerVersion += 1;
    barcodeScannerControls?.stop();
    barcodeScannerControls = null;
    barcodeScanner.hidden = true;
    barcodeScannerMessage.textContent = '';

    if (returnFocus) {
        barcodeScannerButton.focus();
    }
}

barcodeScannerButton?.addEventListener('click', async () => {
    showError(barcodeError);

    setBusy(barcodeScannerButton, true, 'Открываем сканер…');

    try {
        const nativeResult = await scanNativeProductBarcode();

        if (nativeResult.status === 'scanned') {
            applyDetectedBarcode(nativeResult.barcode);
            return;
        }

        if (nativeResult.status === 'cancelled') {
            return;
        }
    } catch (error) {
        showError(
            barcodeError,
            error instanceof Error
                ? error.message
                : 'Не удалось открыть сканер. Введите штрихкод вручную.',
        );
        return;
    } finally {
        setBusy(barcodeScannerButton, false, 'Открываем сканер…');
    }

    const captureMode = barcodeCaptureMode({
        isSecureContext: window.isSecureContext,
        hasMediaDevices: Boolean(navigator.mediaDevices?.getUserMedia),
    });

    if (captureMode === 'photo') {
        barcodeCameraInput.click();
        return;
    }

    closeBarcodeScanner();
    const scannerVersion = barcodeScannerVersion;
    barcodeScanner.hidden = false;
    barcodeScannerMessage.textContent = 'Разрешите доступ к камере и держите код внутри рамки.';
    setBusy(barcodeScannerButton, true, 'Запускаем камеру…');

    try {
        const controls = await startLiveBarcodeScanner({
            videoElement: barcodeScannerVideo,
            onDetected: (barcode) => {
                if (scannerVersion !== barcodeScannerVersion) {
                    return;
                }

                closeBarcodeScanner();
                applyDetectedBarcode(barcode);
            },
        });

        if (scannerVersion !== barcodeScannerVersion) {
            controls.stop();
            return;
        }

        barcodeScannerControls = controls;
        barcodeScannerMessage.textContent = 'Камера готова. Наведите её на штрихкод.';
    } catch {
        if (scannerVersion !== barcodeScannerVersion) {
            return;
        }

        closeBarcodeScanner();
        showError(
            barcodeError,
            'Не удалось открыть камеру. Проверьте разрешение браузера или введите код вручную.',
        );
    } finally {
        setBusy(barcodeScannerButton, false, 'Запускаем камеру…');
    }
});

barcodeCameraInput?.addEventListener('change', async () => {
    const image = barcodeCameraInput.files?.[0];

    if (!image) {
        return;
    }

    showError(barcodeError);
    setBusy(barcodeScannerButton, true, 'Читаем код…');

    try {
        applyDetectedBarcode(await decodeBarcodeImage(image));
    } catch (error) {
        showError(
            barcodeError,
            error instanceof Error ? error.message : 'Не удалось прочитать штрихкод.',
        );
    } finally {
        barcodeCameraInput.value = '';
        setBusy(barcodeScannerButton, false, 'Читаем код…');
    }
});

closeBarcodeScannerButton?.addEventListener('click', () => {
    closeBarcodeScanner({ returnFocus: true });
});

barcodeForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const barcode = barcodeInput.value.trim();
    const submitButton = barcodeForm.querySelector('button[type="submit"]');

    showError(barcodeError);

    if (!/^[0-9A-Za-z._-]{4,32}$/.test(barcode)) {
        showError(barcodeError, 'Введите штрихкод длиной от 4 до 32 символов.');
        barcodeInput.focus();
        return;
    }

    currentBarcode = barcode;
    currentProductName = '';
    setBusy(submitButton, true, 'Ищем товар…');

    try {
        const response = await fetch(
            verdictUrlTemplate.replace('__BARCODE__', encodeURIComponent(barcode)),
            { headers: { Accept: 'application/json' } },
        );
        const payload = await readJson(response);

        if (response.status === 404) {
            showCatalogResult(
                'Товар пока не найден',
                'Добавьте фото всей этикетки для предварительной проверки.',
                'neutral',
            );
            focusIngredientFallback();
            return;
        }

        if (!response.ok) {
            throw new Error('Не удалось проверить каталог. Попробуйте ещё раз.');
        }

        const product = payload.data?.product ?? {};
        const verdict = payload.data?.verdict ?? {};
        const status = catalogStatuses[verdict.status] ?? catalogStatuses.unknown;

        if (verdict.reason_codes?.includes('unknown_product')) {
            showCatalogResult(
                'Товар пока не найден',
                'Добавьте фото всей этикетки для предварительной проверки.',
                'neutral',
            );
            focusIngredientFallback();
            return;
        }

        currentProductName = product.name ?? '';

        const productLabel = [product.name, product.brand].filter(Boolean).join(' · ');
        const resultText = productLabel ? `${productLabel}. ${status.text}` : status.text;

        showCatalogResult(status.title, resultText, status.tone);

        if (verdict.status !== 'halal') {
            focusIngredientFallback();
        }
    } catch (error) {
        showCatalogResult(
            'Проверка временно недоступна',
            error instanceof Error ? error.message : 'Попробуйте ещё раз.',
            'danger',
        );
    } finally {
        setBusy(submitButton, false, 'Ищем товар…');
    }
});

async function handleIngredientImageSelection(input) {
    const selectionVersion = ++imageSelectionVersion;
    const file = input.files?.[0];
    let recognizedTextWasPublished = false;

    showError(imageError);
    showRecognitionMessage();

    if (!file) {
        setBusy(ingredientSubmitButton, false, 'Анализируем товар…');
        clearSelectedImage();
        return;
    }

    if (!acceptedImageTypes.includes(file.type)) {
        setBusy(ingredientSubmitButton, false, 'Анализируем товар…');
        clearSelectedImage({ clearMessages: false });
        showError(imageError, 'Выберите изображение в формате JPG, PNG или WebP.');
        return;
    }

    showSelectedImage(file);
    setBusy(ingredientSubmitButton, true, 'Подготавливаем фото…');

    if (file.size > maximumUploadSize) {
        showRecognitionMessage('Уменьшаем фотографию, сохраняя читаемость текста…');
    } else {
        showRecognitionMessage('Подготавливаем фотографию к распознаванию…');
    }

    try {
        const preparedImage = await prepareImageForUpload(file);

        if (selectionVersion !== imageSelectionVersion) {
            return;
        }

        ingredientImageInputs.forEach((otherInput) => {
            if (otherInput !== input) {
                otherInput.value = '';
            }
        });
        showSelectedImage(preparedImage.file);
        showRecognitionMessage('Распознаём всю этикетку и определяем товар…');

        const result = await processIngredientImage({
            image: preparedImage.file,
            recognize: async (image) => {
                const recognition = await recognizeIngredientImage({
                    image,
                    recognitionUrl,
                });

                if (selectionVersion !== imageSelectionVersion) {
                    throw new Error('stale-image-selection');
                }

                return recognition;
            },
            onRecognized: (recognition) => {
                recognizedTextWasPublished = true;
                ingredientInput.value = recognition.labelText;
                recognizedSourceQuality = recognition.sourceQuality;
                recognizedIngredientsText = recognition.ingredientsText;
                recognizedProductName = recognition.productName;
                recognizedProductType = recognition.productType;
                recognizedProductTypeBasis = recognition.productTypeBasis;
                const productSummary = describeRecognizedProduct(recognition);
                showRecognitionMessage(
                    [
                        productSummary,
                        recognition.sourceQuality === 'partial'
                            ? 'Этикетка распознана частично. Анализируем её — затем проверьте неразборчивые места.'
                            : 'Этикетка распознана. Выполняем предварительную проверку…',
                    ].filter(Boolean).join(' '),
                );
            },
            analyze: async ({
                labelText,
                ingredientsText,
                productName,
                productType,
                productTypeBasis,
                sourceQuality,
            }) => {
                if (selectionVersion !== imageSelectionVersion) {
                    throw new Error('stale-image-selection');
                }

                setBusy(ingredientSubmitButton, true, 'Анализируем товар…');

                return analyzeIngredients({
                    labelText,
                    ingredientsText,
                    sourceQuality,
                    barcode: currentBarcode,
                    productName: productName || currentProductName,
                    productType,
                    productTypeBasis,
                    analysisUrl,
                });
            },
        });

        if (selectionVersion !== imageSelectionVersion) {
            return;
        }

        renderAnalysis(result.analysis);
        const productSummary = describeRecognizedProduct(result.recognition);
        showRecognitionMessage(
            [
                productSummary,
                result.recognition.sourceQuality === 'partial'
                    ? 'Фото проверено. Исправьте неразборчивые места и отправьте текст заново.'
                    : 'Фото проверено. При необходимости исправьте текст этикетки и отправьте заново.',
            ].filter(Boolean).join(' '),
        );
    } catch (error) {
        if (selectionVersion !== imageSelectionVersion) {
            return;
        }

        if (recognizedTextWasPublished) {
            const message = error instanceof Error
                ? error.message
                : 'Попробуйте повторить анализ позже.';
            renderAnalysisError(message);
            showRecognitionMessage(
                'Текст этикетки распознан и сохранён. Проверьте его и нажмите «Проверить исправленный текст».',
            );
        } else {
            showError(
                imageError,
                error instanceof Error
                    ? error.message
                    : 'Не удалось распознать этикетку и товар на фотографии.',
            );
            showRecognitionMessage();
        }
    } finally {
        if (selectionVersion === imageSelectionVersion) {
            setBusy(ingredientSubmitButton, false, 'Анализируем товар…');
        }
    }
}

ingredientImageInputs.forEach((input) => {
    input.addEventListener('change', () => {
        void handleIngredientImageSelection(input);
    });
});

removeImageButton?.addEventListener('click', () => {
    imageSelectionVersion += 1;
    setBusy(ingredientSubmitButton, false, 'Анализируем товар…');
    clearSelectedImage();
    ingredientImageInput.focus();
});

window.addEventListener('pagehide', () => {
    revokePreviewUrl();
    closeBarcodeScanner();
});

ingredientInput?.addEventListener('input', () => {
    recognizedIngredientsText = '';
    recognizedProductName = '';
    recognizedProductType = '';
    recognizedProductTypeBasis = '';

    if (ingredientInput.value.trim() === '') {
        recognizedSourceQuality = null;
    }
});

function addAttentionItem(item) {
    const row = document.createElement('li');
    row.className = 'attention-item';

    const icon = document.createElement('span');
    icon.className = 'attention-item__icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '!';

    const name = document.createElement('strong');
    name.textContent = item.ingredient;

    const reason = document.createElement('p');
    reason.textContent = item.reason;

    row.append(icon, name, reason);
    attentionList.append(row);
}

function addClearItem() {
    const row = document.createElement('li');
    row.className = 'attention-item attention-item--clear';

    const icon = document.createElement('span');
    icon.className = 'attention-item__icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '✓';

    const name = document.createElement('strong');
    name.textContent = 'Дополнительных отметок нет';

    row.append(icon, name);
    attentionList.append(row);
}

function addIncompleteItem() {
    const row = document.createElement('li');
    row.className = 'attention-item attention-item--neutral';

    const icon = document.createElement('span');
    icon.className = 'attention-item__icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '?';

    const name = document.createElement('strong');
    name.textContent = 'Полный состав не предоставлен';

    row.append(icon, name);
    attentionList.append(row);
}

function renderAnalysis(payload) {
    const analysis = payload.analysis ?? {};
    const screening = payload.screening ?? {};
    const status = screeningPresentation(screening.status, screening.reason_codes);
    const items = Array.isArray(analysis.attention_items) ? analysis.attention_items : [];

    analysisPanel.classList.remove(
        'analysis-panel--warning',
        'analysis-panel--danger',
        'analysis-panel--clear',
        'analysis-panel--neutral',
    );
    analysisPanel.classList.add(`analysis-panel--${status.tone}`);
    analysisEmpty.hidden = true;
    analysisResult.hidden = false;
    analysisStatusIcon.textContent = status.icon;
    analysisStatus.textContent = status.title;
    analysisSummary.textContent = screening.status === 'insufficient_text'
        ? 'Запрещённых компонентов в распознанном фрагменте не обнаружено. Загрузите полный состав или проверьте инструкцию производителя.'
        : (analysis.summary ?? 'Результат требует дополнительной проверки.');
    attentionList.replaceChildren();

    if (items.length === 0) {
        if (screening.status === 'insufficient_text') {
            addIncompleteItem();
        } else {
            addClearItem();
        }
    } else {
        items.forEach(addAttentionItem);
    }

    if (window.matchMedia('(max-width: 820px)').matches) {
        analysisPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function renderAnalysisError(message) {
    analysisPanel.classList.remove('analysis-panel--warning', 'analysis-panel--danger', 'analysis-panel--clear');
    analysisPanel.classList.add('analysis-panel--neutral');
    analysisEmpty.hidden = true;
    analysisResult.hidden = false;
    analysisStatusIcon.textContent = '!';
    analysisStatus.textContent = 'Анализ временно недоступен';
    analysisSummary.textContent = message;
    attentionList.replaceChildren();
}

ingredientForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const labelText = ingredientInput.value.trim();

    showError(ingredientError);

    if (labelText.length < 3) {
        showError(ingredientError, 'Введите текст этикетки длиной не менее трёх символов.');
        ingredientInput.focus();
        return;
    }

    setBusy(ingredientSubmitButton, true, 'Анализируем…');

    try {
        const analysis = await analyzeIngredients({
            labelText,
            ingredientsText: recognizedIngredientsText,
            sourceQuality: recognizedSourceQuality,
            barcode: currentBarcode,
            productName: recognizedProductName || currentProductName,
            productType: recognizedProductType,
            productTypeBasis: recognizedProductTypeBasis,
            analysisUrl,
        });

        renderAnalysis(analysis);
    } catch (error) {
        renderAnalysisError(error instanceof Error ? error.message : 'Попробуйте повторить анализ позже.');
    } finally {
        setBusy(ingredientSubmitButton, false, 'Анализируем…');
    }
});
