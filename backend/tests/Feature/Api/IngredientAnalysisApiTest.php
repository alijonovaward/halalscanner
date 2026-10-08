<?php

namespace Tests\Feature\Api;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IngredientAnalysisApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('services.gemini.api_key', 'test-key');
        config()->set('services.gemini.model', 'gemini-flash-latest');
    }

    public function test_it_returns_a_structured_advisory_analysis_without_a_halal_verdict(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse(), 200),
        ]);

        $this->postJson('/api/ingredient-analyses', [
            'product_name' => 'Fruit candy',
            'barcode' => '4780042470202',
            'ingredients_text' => 'Sugar, gelatin, flavouring',
        ])
            ->assertOk()
            ->assertJsonPath('data.analysis.normalized_ingredients.1.name', 'gelatin')
            ->assertJsonPath('data.analysis.attention_items.0.category', 'animal_origin')
            ->assertJsonPath('data.screening.status', 'needs_review')
            ->assertJsonPath('data.screening.advisory_only', true)
            ->assertJsonPath('data.provider.name', 'gemini')
            ->assertJsonPath('data.provider.model', 'gemini-flash-latest')
            ->assertJsonMissingPath('data.verdict');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return str_contains($request->url(), 'gemini-flash-latest:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && data_get($payload, 'generationConfig.responseMimeType') === 'application/json'
                && data_get($payload, 'generationConfig.responseSchema.type') === 'OBJECT'
                && str_contains((string) data_get($payload, 'contents.0.parts.0.text'), 'Always write the summary and reasons in Russian')
                && str_contains((string) data_get($payload, 'contents.0.parts.0.text'), 'Sugar, gelatin, flavouring');
        });
    }

    public function test_it_validates_the_ingredient_text_before_calling_gemini(): void
    {
        Http::fake();

        $this->postJson('/api/ingredient-analyses', [
            'ingredients_text' => '  ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ingredients_text');

        Http::assertNothingSent();
    }

    public function test_it_caches_identical_analyses(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse(), 200),
        ]);

        $payload = ['ingredients_text' => 'Sugar, gelatin, flavouring'];

        $this->postJson('/api/ingredient-analyses', $payload)->assertOk();
        $this->postJson('/api/ingredient-analyses', $payload)->assertOk();

        Http::assertSentCount(1);
    }

    public function test_it_accepts_an_empty_attention_list_without_claiming_a_halal_verdict(): void
    {
        $response = $this->geminiResponse();
        $analysis = json_decode(
            $response['candidates'][0]['content']['parts'][0]['text'],
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $analysis['attention_items'] = [];
        $analysis['summary'] = 'No terms need additional review.';
        $response['candidates'][0]['content']['parts'][0]['text'] = json_encode($analysis, JSON_THROW_ON_ERROR);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($response, 200),
        ]);

        $this->postJson('/api/ingredient-analyses', [
            'ingredients_text' => 'Water, sugar',
        ])
            ->assertOk()
            ->assertJsonPath('data.analysis.attention_items', [])
            ->assertJsonPath('data.screening.status', 'no_obvious_concerns')
            ->assertJsonMissingPath('data.verdict');
    }

    public function test_partial_ocr_text_is_never_reported_as_clear(): void
    {
        $response = $this->geminiResponse();
        $analysis = json_decode(
            $response['candidates'][0]['content']['parts'][0]['text'],
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $analysis['normalized_ingredients'] = [
            ['name' => 'сахар', 'source_text' => 'сахара'],
            ['name' => 'хинолиновый жёлтый', 'source_text' => 'краситель хинолин жёлтый'],
        ];
        $analysis['attention_items'] = [];
        $analysis['summary'] = 'Явных рисков не найдено.';
        $response['candidates'][0]['content']['parts'][0]['text'] = json_encode(
            $analysis,
            JSON_THROW_ON_ERROR,
        );

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($response, 200),
        ]);

        $this->postJson('/api/ingredient-analyses', [
            'ingredients_text' => 'Один пакетик содержит 19,8 г сахара. Краситель хинолиновый жёлтый.',
            'source_quality' => 'partial',
        ])
            ->assertOk()
            ->assertJsonPath('data.analysis.input_quality', 'partial')
            ->assertJsonPath('data.screening.status', 'insufficient_text')
            ->assertJsonPath('data.screening.reason_codes.0', 'incomplete_source_text')
            ->assertJsonPath('data.screening.advisory_only', true);

        Http::assertSent(function (Request $request): bool {
            $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text');

            return str_contains($prompt, '"source_quality":"partial"')
                && str_contains($prompt, 'Do not flag sugar')
                && str_contains($prompt, 'synthetic colours');
        });
    }

    public function test_explicit_pork_is_reported_as_haram_even_when_ocr_text_is_partial(): void
    {
        $response = $this->geminiResponse();
        $analysis = json_decode(
            $response['candidates'][0]['content']['parts'][0]['text'],
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $analysis['normalized_ingredients'] = [
            ['name' => 'шпик', 'source_text' => 'шпик'],
            ['name' => 'соль пищевая', 'source_text' => 'соль пищевая'],
        ];
        $analysis['attention_items'] = [[
            'ingredient' => 'шпик',
            'category' => 'pork',
            'evidence_basis' => 'ingredients_text',
            'reason' => 'Шпик является продуктом из свиного жира.',
        ]];
        $analysis['summary'] = 'В составе явно указан продукт свиного происхождения.';
        $response['candidates'][0]['content']['parts'][0]['text'] = json_encode(
            $analysis,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($response, 200),
        ]);

        $this->postJson('/api/ingredient-analyses', [
            'ingredients_text' => 'шпик, соль пищевая',
            'source_quality' => 'partial',
        ])
            ->assertOk()
            ->assertJsonPath('data.analysis.attention_items.0.category', 'pork')
            ->assertJsonPath('data.screening.status', 'haram')
            ->assertJsonPath('data.screening.reason_codes.0', 'contains_pork')
            ->assertJsonPath('data.screening.advisory_only', true);
    }

    public function test_an_explicit_intoxicating_product_label_is_haram_without_an_ingredient_list(): void
    {
        $response = $this->geminiResponse();
        $analysis = json_decode(
            $response['candidates'][0]['content']['parts'][0]['text'],
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $analysis['normalized_ingredients'] = [];
        $analysis['attention_items'] = [[
            'ingredient' => 'ВОДКА',
            'category' => 'intoxicating_beverage',
            'evidence_basis' => 'label_text',
            'reason' => 'На этикетке товар прямо обозначен как водка.',
        ]];
        $analysis['summary'] = 'Товар прямо обозначен как опьяняющий напиток.';
        $response['candidates'][0]['content']['parts'][0]['text'] = json_encode(
            $analysis,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($response, 200),
        ]);

        $this->postJson('/api/ingredient-analyses', [
            'label_text' => "ВОДКА\nСеверная",
            'product_name' => 'Северная',
            'product_type' => 'водка',
            'product_type_basis' => 'label',
            'source_quality' => 'partial',
        ])
            ->assertOk()
            ->assertJsonPath('data.analysis.attention_items.0.category', 'intoxicating_beverage')
            ->assertJsonPath('data.analysis.attention_items.0.evidence_basis', 'label_text')
            ->assertJsonPath('data.screening.status', 'haram')
            ->assertJsonPath('data.screening.reason_codes.0', 'contains_intoxicating_beverage');

        Http::assertSent(function (Request $request): bool {
            $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text');

            return str_contains($prompt, 'ВОДКА')
                && str_contains($prompt, 'Visual appearance alone')
                && str_contains($prompt, 'без алкоголя');
        });
    }

    public function test_a_visual_only_intoxicating_product_identification_requires_review(): void
    {
        $response = $this->geminiResponse();
        $analysis = json_decode(
            $response['candidates'][0]['content']['parts'][0]['text'],
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $analysis['input_quality'] = 'partial';
        $analysis['normalized_ingredients'] = [];
        $analysis['attention_items'] = [[
            'ingredient' => 'бутылка, похожая на водку',
            'category' => 'intoxicating_beverage',
            'evidence_basis' => 'visual',
            'reason' => 'Тип товара определён только по внешнему виду упаковки.',
        ]];
        $analysis['summary'] = 'Внешний вид похож на алкогольный напиток, но надпись не читается.';
        $response['candidates'][0]['content']['parts'][0]['text'] = json_encode(
            $analysis,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($response, 200),
        ]);

        $this->postJson('/api/ingredient-analyses', [
            'product_type' => 'водка',
            'product_type_basis' => 'visual',
            'source_quality' => 'partial',
        ])
            ->assertOk()
            ->assertJsonPath('data.screening.status', 'needs_review')
            ->assertJsonPath('data.screening.reason_codes.0', 'visual_identification_unverified');
    }

    public function test_it_validates_the_optional_source_quality(): void
    {
        Http::fake();

        $this->postJson('/api/ingredient-analyses', [
            'ingredients_text' => 'Sugar, water',
            'source_quality' => 'guessed',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_quality');

        Http::assertNothingSent();
    }

    public function test_it_returns_a_safe_error_when_gemini_is_unavailable(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
        ]);

        $this->postJson('/api/ingredient-analyses', [
            'ingredients_text' => 'Sugar, gelatin, flavouring',
        ])
            ->assertStatus(502)
            ->assertJsonPath('code', 'ai_provider_error');
    }

    public function test_it_returns_a_safe_error_when_gemini_cannot_be_reached(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::failedConnection(),
        ]);

        $this->postJson('/api/ingredient-analyses', [
            'ingredients_text' => 'Sugar, gelatin, flavouring',
        ])
            ->assertStatus(502)
            ->assertJsonPath('code', 'ai_provider_error');
    }

    public function test_it_returns_service_unavailable_when_the_api_key_is_missing(): void
    {
        config()->set('services.gemini.api_key');
        Http::fake();

        $this->postJson('/api/ingredient-analyses', [
            'ingredients_text' => 'Sugar, gelatin, flavouring',
        ])
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'ai_analyzer_unavailable');

        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiResponse(): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode([
                            'detected_language' => 'en',
                            'input_quality' => 'complete',
                            'normalized_ingredients' => [
                                ['name' => 'sugar', 'source_text' => 'Sugar'],
                                ['name' => 'gelatin', 'source_text' => 'gelatin'],
                                ['name' => 'flavouring', 'source_text' => 'flavouring'],
                            ],
                            'attention_items' => [[
                                'ingredient' => 'gelatin',
                                'category' => 'animal_origin',
                                'evidence_basis' => 'ingredients_text',
                                'reason' => 'Animal source is not declared.',
                            ]],
                            'summary' => 'Gelatin origin requires verification.',
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ],
            ]],
        ];
    }
}
