import assert from 'node:assert/strict';
import test from 'node:test';

class FakeElement extends EventTarget {
    constructor() {
        super();
        this.classList = {
            add() {},
            remove() {},
            toggle() {},
        };
        this.dataset = {};
        this.disabled = false;
        this.files = [];
        this.hidden = false;
        this.textContent = '';
        this.value = '';
    }

    click() {}

    focus() {}

    querySelector() {
        return this.buttonLabel;
    }

    removeAttribute(attribute) {
        delete this[attribute];
    }

    replaceChildren() {}

    requestSubmit() {}

    scrollIntoView() {}

    setAttribute(attribute, value) {
        this[attribute] = value;
    }
}

async function loadScannerPage() {
    const elements = new Map();
    const selectors = [
        '#barcode-form',
        '#barcode',
        '#barcode-error',
        '#start-barcode-scanner',
        '#barcode-camera-input',
        '#barcode-scanner',
        '#barcode-scanner-video',
        '#barcode-scanner-message',
        '#close-barcode-scanner',
        '#catalog-result',
        '#catalog-result-title',
        '#catalog-result-text',
        '#ingredient-form',
        '#ingredients-text',
        '#ingredient-error',
        '#ingredient-image',
        '#ingredient-camera',
        '#image-preview',
        '#image-preview-picture',
        '#image-file-name',
        '#image-error',
        '#recognition-message',
        '#remove-image',
        '#analysis-panel',
        '#analysis-empty',
        '#analysis-result',
        '#analysis-status-icon',
        '#analysis-status',
        '#analysis-summary',
        '#attention-list',
    ];

    selectors.forEach((selector) => elements.set(selector, new FakeElement()));

    const ingredientSubmitButton = new FakeElement();
    ingredientSubmitButton.buttonLabel = new FakeElement();
    ingredientSubmitButton.buttonLabel.textContent = 'Проверить исправленный состав';
    elements.get('#ingredient-form').buttonLabel = ingredientSubmitButton;

    const barcodeSubmitButton = new FakeElement();
    barcodeSubmitButton.buttonLabel = new FakeElement();
    barcodeSubmitButton.buttonLabel.textContent = 'Проверить';
    elements.get('#barcode-form').buttonLabel = barcodeSubmitButton;

    const body = new FakeElement();
    body.dataset = {
        analysisUrl: '/api/ingredient-analyses',
        recognitionUrl: '/api/ingredient-image-recognitions',
        verdictUrlTemplate: '/api/products/__BARCODE__/verdict',
    };

    const originalDocument = globalThis.document;
    const originalWindow = globalThis.window;
    const originalCreateObjectUrl = URL.createObjectURL;
    const originalRevokeObjectUrl = URL.revokeObjectURL;

    globalThis.document = {
        body,
        createElement: () => new FakeElement(),
        querySelector: (selector) => elements.get(selector) ?? null,
    };
    globalThis.window = {
        addEventListener() {},
        isSecureContext: true,
        matchMedia: () => ({ matches: false }),
    };
    URL.createObjectURL = () => 'blob:test-preview';
    URL.revokeObjectURL = () => {};

    await import(`../../resources/js/app.js?test=${Date.now()}-${Math.random()}`);

    return {
        imageInput: elements.get('#ingredient-image'),
        ingredientSubmitButton,
        restoreGlobals() {
            globalThis.document = originalDocument;
            globalThis.window = originalWindow;
            URL.createObjectURL = originalCreateObjectUrl;
            URL.revokeObjectURL = originalRevokeObjectUrl;
        },
    };
}

function startPendingSelection(imageInput) {
    imageInput.files = [new File(['photo'], 'ingredients.jpg', { type: 'image/jpeg' })];
    imageInput.dispatchEvent(new Event('change'));
}

test('invalid replacement releases ingredient analysis while the previous photo is pending', async () => {
    const page = await loadScannerPage();

    try {
        startPendingSelection(page.imageInput);

        assert.equal(page.ingredientSubmitButton.disabled, true);

        page.imageInput.files = [new File(['notes'], 'ingredients.txt', { type: 'text/plain' })];
        page.imageInput.dispatchEvent(new Event('change'));

        await Promise.resolve();

        assert.equal(page.ingredientSubmitButton.disabled, false);
    } finally {
        page.restoreGlobals();
    }
});

test('clearing selection releases ingredient analysis while the previous photo is pending', async () => {
    const page = await loadScannerPage();

    try {
        startPendingSelection(page.imageInput);

        assert.equal(page.ingredientSubmitButton.disabled, true);

        page.imageInput.files = [];
        page.imageInput.dispatchEvent(new Event('change'));

        await Promise.resolve();

        assert.equal(page.ingredientSubmitButton.disabled, false);
    } finally {
        page.restoreGlobals();
    }
});
