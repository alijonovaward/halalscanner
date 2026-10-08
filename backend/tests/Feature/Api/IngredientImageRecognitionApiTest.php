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

    public function test_it_reads_the_whole_label_and_identifies_the_product_without_issuing_a_verdict(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse(), 200),
        ]);

        $this->post('/api/ingredient-image-recognitions', [
            'image' => UploadedFile::fake()->image('label.jpg', 1200, 900),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.label_text', "ВОДКА\nСостав: вода подготовленная, спирт этиловый ректификованный")
            ->assertJsonPath('data.product_name', 'Северная')
            ->assertJsonPath('data.product_type', 'водка')
            ->assertJsonPath('data.product_type_basis', 'label')
            ->assertJsonPath('data.ingredients_text', 'Сахар, желатин, ароматизатор')
            ->assertJsonPath('data.quality', 'complete')
            ->assertJsonPath('data.detected_language', 'ru');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return str_contains($request->url(), 'gemini-flash-latest:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && data_get($payload, 'contents.0.parts.0.inlineData.mimeType') === 'image/jpeg'
                && is_string(data_get($payload, 'contents.0.parts.0.inlineData.data'))
                && data_get($payload, 'contents.0.parts.0.inlineData.data') !== ''
                && data_get($payload, 'generationConfig.responseMimeType') === 'application/json'
                && data_get($payload, 'generationConfig.responseSchema.type') === 'OBJECT'
                && str_contains(
                    (string) data_get($payload, 'contents.0.parts.1.text'),
                    'entire visible product label',
                );
        });
    }

    public function test_it_validates_the_uploaded_image_before_calling_gemini(): void
    {
        Http::fake();

        $this->post('/api/ingredient-image-recognitions', [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->post('/api/ingredient-image-recognitions', [
            'image' => UploadedFile::fake()->create('ingredients.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->post('/api/ingredient-image-recognitions', [
            'image' => UploadedFile::fake()->create('large.jpg', 8193, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        Http::assertNothingSent();
    }

    public function test_it_explains_the_image_size_limit_in_russian(): void
    {
        Http::fake();

        $this->post('/api/ingredient-image-recognitions', [
            'image' => UploadedFile::fake()->create('large.jpg', 8193, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'Размер изображения не должен превышать 8 МБ.');

        Http::assertNothingSent();
    }

    public function test_it_returns_an_unreadable_result_without_transcribed_text(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                $this->geminiResponse('unreadable', 'Invented text'),
                200,
            ),
        ]);

        $this->post('/api/ingredient-image-recognitions', [
            'image' => UploadedFile::fake()->image('blurred.webp'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.ingredients_text', '')
            ->assertJsonPath('data.quality', 'unreadable');
    }

    public function test_it_returns_a_safe_error_when_gemini_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
        ]);

        $this->post('/api/ingredient-image-recognitions', [
            'image' => UploadedFile::fake()->image('label.png'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'ai_provider_error')
            ->assertJsonMissingPath('error');
    }

    public function test_it_returns_service_unavailable_when_the_api_key_is_missing(): void
    {
        config()->set('services.gemini.api_key');
        Http::fake();

        $this->post('/api/ingredient-image-recognitions', [
            'image' => UploadedFile::fake()->image('label.png'),
        ], ['Accept' => 'application/json'])
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'ai_analyzer_unavailable');

        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiResponse(
        string $quality = 'complete',
        string $ingredientsText = 'Сахар, желатин, ароматизатор',
    ): array {
        return [
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode([
                            'label_text' => "ВОДКА\nСостав: вода подготовленная, спирт этиловый ректификованный",
                            'product_name' => 'Северная',
                            'product_type' => 'водка',
                            'product_type_basis' => 'label',
                            'ingredients_text' => $ingredientsText,
                            'quality' => $quality,
                            'detected_language' => 'ru',
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ],
            ]],
        ];
    }
}
