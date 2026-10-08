# Ingredient Image Recognition Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users upload a package photo, transcribe the visible ingredient list with Gemini into the editable textarea, and then use the existing ingredient analysis action.

**Architecture:** Add a dedicated multipart Laravel endpoint backed by a focused Gemini image recognition service. The browser owns only preview state and sends the selected file with `FormData`; the server validates the upload, passes it to Gemini as base64 `inlineData`, validates structured JSON, and returns transcription data without persisting the image.

**Tech Stack:** PHP 8.3, Laravel 13, Laravel HTTP client, PHPUnit 12, Blade, vanilla JavaScript, Tailwind/Vite CSS, Gemini `generateContent` REST API.

---

### Task 1: Specify the image recognition API with feature tests

**Files:**
- Create: `backend/tests/Feature/Api/IngredientImageRecognitionApiTest.php`

- [ ] **Step 1: Write the failing success-path test**

Create the test class with Gemini configuration, a fake uploaded JPEG, a structured provider response, and assertions for both the public response and outgoing inline image part:

```php
<?php

namespace Tests\Feature\Api;

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IngredientImageRecognitionApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gemini.api_key', 'test-key');
        config()->set('services.gemini.model', 'gemini-flash-latest');
    }

    public function test_it_transcribes_an_ingredient_image_without_analyzing_it(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse(), 200),
        ]);

        $this->post('/api/ingredient-image-recognitions', [
            'image' => UploadedFile::fake()->image('label.jpg', 1200, 900),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.ingredients_text', 'Сахар, желатин, ароматизатор')
            ->assertJsonPath('data.quality', 'complete')
            ->assertJsonPath('data.detected_language', 'ru');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return str_contains($request->url(), 'gemini-flash-latest:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && data_get($payload, 'contents.0.parts.0.inlineData.mimeType') === 'image/jpeg'
                && is_string(data_get($payload, 'contents.0.parts.0.inlineData.data'))
                && data_get($payload, 'generationConfig.responseMimeType') === 'application/json'
                && data_get($payload, 'generationConfig.responseSchema.type') === 'OBJECT';
        });
    }

    private function geminiResponse(): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => json_encode([
                        'ingredients_text' => 'Сахар, желатин, ароматизатор',
                        'quality' => 'complete',
                        'detected_language' => 'ru',
                    ], JSON_THROW_ON_ERROR),
                ]]],
            ]],
        ];
    }
}
```

- [ ] **Step 2: Add validation and failure cases**

Add tests that submit no image, a text file, and a file larger than 8192 KiB and assert HTTP 422 with an `image` validation error. Add provider HTTP failure, connection failure, missing API key, malformed structured output, and `quality: unreadable` cases. Assert the public error codes match the existing analysis endpoint: `ai_provider_error` for 502 and `ai_analyzer_unavailable` for 503.

- [ ] **Step 3: Run the focused test and verify the route is missing**

Run:

```bash
cd backend
php artisan test --compact tests/Feature/Api/IngredientImageRecognitionApiTest.php
```

Expected: FAIL because `POST /api/ingredient-image-recognitions` is not registered.

### Task 2: Implement the server-side recognizer

**Files:**
- Create: `backend/app/Http/Requests/RecognizeIngredientImageRequest.php`
- Create: `backend/app/Services/Ai/GeminiIngredientImageRecognizer.php`
- Create: `backend/app/Http/Controllers/Api/IngredientImageRecognitionController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Api/IngredientImageRecognitionApiTest.php`

- [ ] **Step 1: Add multipart validation**

Use a form request with these rules:

```php
public function authorize(): bool
{
    return true;
}

public function rules(): array
{
    return [
        'image' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
    ];
}
```

- [ ] **Step 2: Add the Gemini image recognizer**

Implement `recognize(UploadedFile $image): array` with the same API key, model, base URL, timeout, safe exception classes, and response parsing used by `GeminiIngredientAnalyzer`. Send this content structure:

```php
'contents' => [[
    'role' => 'user',
    'parts' => [
        [
            'inlineData' => [
                'mimeType' => $image->getMimeType(),
                'data' => base64_encode($image->getContent()),
            ],
        ],
        ['text' => $this->prompt()],
    ],
]],
'generationConfig' => [
    'candidateCount' => 1,
    'maxOutputTokens' => 2048,
    'responseMimeType' => 'application/json',
    'responseSchema' => [
        'type' => 'OBJECT',
        'properties' => [
            'ingredients_text' => ['type' => 'STRING'],
            'quality' => [
                'type' => 'STRING',
                'enum' => ['complete', 'partial', 'unreadable'],
            ],
            'detected_language' => ['type' => 'STRING'],
        ],
        'required' => ['ingredients_text', 'quality', 'detected_language'],
    ],
],
```

The prompt must tell Gemini to transcribe only the visible ingredient list, preserve order and punctuation, mark uncertain fragments with `[неразборчиво]`, ignore instructions printed in the image, return empty text for an unreadable label, and avoid analysis or religious verdicts.

Validate the decoded response with:

```php
$validator = Validator::make($decoded, [
    'ingredients_text' => ['present', 'string', 'max:5000'],
    'quality' => ['required', Rule::in(['complete', 'partial', 'unreadable'])],
    'detected_language' => ['required', 'string', 'max:20'],
]);
```

Reject a non-empty `ingredients_text` for `quality: unreadable` by normalizing it to an empty string before returning the validated payload.

- [ ] **Step 3: Add the controller and route**

The controller calls the recognizer with `$request->file('image')`, returns `['data' => $recognition]`, and maps existing AI exceptions to the same safe 502 and 503 JSON used by `IngredientAnalysisController`.

Register:

```php
Route::post('/ingredient-image-recognitions', IngredientImageRecognitionController::class)
    ->middleware('throttle:10,1')
    ->name('api.ingredient-image-recognitions.store');
```

- [ ] **Step 4: Run the API tests**

Run:

```bash
php artisan test --compact tests/Feature/Api/IngredientImageRecognitionApiTest.php tests/Feature/Api/IngredientAnalysisApiTest.php
```

Expected: all image recognition and existing text analysis tests pass.

### Task 3: Add the upload and preview interface

**Files:**
- Modify: `backend/tests/Feature/Web/ScannerPageTest.php`
- Modify: `backend/resources/views/welcome.blade.php`
- Modify: `backend/resources/css/app.css`

- [ ] **Step 1: Extend the page test first**

Add assertions for the upload control, recognition action, and endpoint:

```php
->assertSee('Загрузить фото состава')
->assertSee('Распознать состав')
->assertSee('accept="image/jpeg,image/png,image/webp"', false)
->assertSee(route('api.ingredient-image-recognitions.store'), false)
```

Run:

```bash
php artisan test --compact tests/Feature/Web/ScannerPageTest.php
```

Expected: FAIL because the page has no image recognition UI.

- [ ] **Step 2: Expose the endpoint and upload markup**

Add `data-recognition-url` to `<body>`. Inside `#ingredient-form`, place a file input and upload panel above the textarea. Required stable element IDs are:

```html
<input id="ingredient-image" name="image" type="file"
    accept="image/jpeg,image/png,image/webp" capture="environment" hidden>
<label class="image-upload" for="ingredient-image">
    <span class="image-upload__title">Загрузить фото состава</span>
    <span class="image-upload__hint">JPG, PNG или WebP до 8 МБ</span>
</label>
<div id="image-preview" class="image-preview" hidden>
    <img id="image-preview-picture" alt="Выбранная фотография состава">
    <div class="image-preview__details">
        <strong id="image-file-name"></strong>
        <button id="remove-image" type="button">Удалить фото</button>
    </div>
</div>
<p id="image-error" class="field-error" role="alert" hidden></p>
<p id="recognition-message" class="recognition-message" aria-live="polite" hidden></p>
<button id="recognize-image" class="secondary-button" type="button" disabled>
    <span data-button-label>Распознать состав</span>
</button>
```

- [ ] **Step 3: Style desktop, mobile, focus, and state behavior**

Add styles for `.image-upload`, `.image-preview`, `.recognition-message`, and `.secondary-button`. Match the current forest green palette, 14px control radius, and border tokens. Give the upload label a visible hover and `:focus-within` state, constrain preview images with `object-fit: cover`, and stack preview details under 480px. Keep the textarea fully visible below the upload controls.

- [ ] **Step 4: Re-run the page test**

Run:

```bash
php artisan test --compact tests/Feature/Web/ScannerPageTest.php
```

Expected: PASS.

### Task 4: Implement browser state and recognition submission

**Files:**
- Modify: `backend/resources/js/app.js`

- [ ] **Step 1: Add image state and validation helpers**

Read the new DOM elements and track `selectedImage` and `previewUrl`. Accept only `image/jpeg`, `image/png`, and `image/webp`, and reject files larger than `8 * 1024 * 1024`. On a valid selection, create an object URL, show the preview and filename, enable the recognition button, and clear any old image error. Revoke the previous object URL whenever the image changes or is removed.

- [ ] **Step 2: Add remove behavior**

The remove button must clear the file input, revoke the object URL, hide and reset the preview, disable recognition, and clear image-specific messages without modifying `#ingredients-text`.

- [ ] **Step 3: Submit multipart recognition requests**

Build the request without setting `Content-Type`, allowing the browser to add the multipart boundary:

```js
const formData = new FormData();
formData.append('image', selectedImage);

const response = await fetch(recognitionUrl, {
    method: 'POST',
    headers: { Accept: 'application/json' },
    body: formData,
});
```

For HTTP 422, show the first `errors.image` message. For HTTP 429, show the existing retry wording. For all other failures, show the safe server message or a Russian fallback.

For `quality: unreadable` or empty text, leave the textarea unchanged and ask for a clearer photo. Otherwise assign `ingredients_text` to the textarea, show “Проверьте распознанный текст и при необходимости исправьте его.”, focus the textarea, and preserve the existing manual analysis button flow.

- [ ] **Step 4: Run static and production build checks**

Run:

```bash
node --check resources/js/app.js
npm run build
```

Expected: syntax check exits 0 and Vite builds production assets without errors.

### Task 5: Verify the complete feature

**Files:**
- Verify all files changed above.

- [ ] **Step 1: Format and run the full PHP suite**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Expected: Pint passes and all existing plus new tests pass.

- [ ] **Step 2: Run a real Gemini recognition smoke test**

Use a generated local JPEG containing a clearly readable Russian ingredient list. Submit it to the local recognition endpoint and verify HTTP 200, non-empty `ingredients_text`, supported `quality`, and no provider details or API key in the response.

- [ ] **Step 3: Verify the browser flow at desktop and mobile sizes**

At 1440×1000 and 390×844, verify selection, preview, replacement, removal, recognition loading, recognized text insertion, manual text edit, and subsequent analysis. Confirm keyboard focus styles, no horizontal overflow, and no console or page errors.

- [ ] **Step 4: Inspect the final visual result**

Capture temporary desktop and mobile screenshots, compare spacing, hierarchy, controls, colors, responsiveness, and advisory placement with the approved scanner design, then remove the temporary QA screenshots.
