<?php

namespace Tests\Feature\Database;

use App\Models\ProductCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCandidateDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_candidates_have_separate_staging_storage(): void
    {
        $this->assertTrue(Schema::hasColumns('product_candidates', [
            'barcode',
            'name',
            'brand',
            'ingredients_text',
            'source_url',
            'source_modified_at',
            'source_dataset',
            'source_license',
            'market_tags',
            'review_status',
            'data_quality_flags',
        ]));
    }

    public function test_product_candidate_model_is_available(): void
    {
        $this->assertTrue(class_exists(ProductCandidate::class));
    }

    public function test_product_candidate_preserves_source_and_review_metadata(): void
    {
        $candidate = ProductCandidate::factory()->create([
            'barcode' => '4780042470202',
            'name' => 'Молоко Lactel 1%',
            'brand' => 'Lactel',
            'ingredients_text' => 'Молоко коровье нормализованное',
            'source_url' => 'https://world.openfoodfacts.org/product/4780042470202',
            'source_modified_at' => '2026-09-18T12:00:00+00:00',
            'source_dataset' => 'Open Food Facts',
            'source_license' => 'ODbL-1.0',
            'market_tags' => ['en:uzbekistan'],
            'data_quality_flags' => ['no_ingredients_image_in_source'],
        ]);

        $this->assertSame('pending_review', $candidate->review_status);
        $this->assertSame(['en:uzbekistan'], $candidate->market_tags);
        $this->assertSame(['no_ingredients_image_in_source'], $candidate->data_quality_flags);
        $this->assertSame('2026-09-18', $candidate->source_modified_at->toDateString());
    }
}
