<?php

namespace Tests\Feature\Api;

use App\Domain\Halal\EvidenceDirection;
use App\Domain\Halal\EvidenceStrength;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\SourceType;
use App\Domain\Halal\TrustTier;
use App\Models\Certificate;
use App\Models\CertificationBody;
use App\Models\Evidence;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVerdictApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_product_verdict_by_barcode(): void
    {
        $product = Product::factory()->create([
            'barcode' => '4780000000036',
            'name' => 'Verified Yogurt',
            'brand' => 'Test Brand',
        ]);

        $body = CertificationBody::factory()->create([
            'name' => 'Trusted Halal Authority',
            'trust_status' => 'trusted',
        ]);

        $certificate = Certificate::factory()
            ->for($product)
            ->for($body)
            ->create([
                'certificate_number' => 'THA-API-001',
                'status' => 'valid',
                'verified_at' => now(),
            ]);

        Evidence::factory()
            ->for($product)
            ->for($certificate)
            ->create([
                'source_type' => SourceType::Certificate,
                'trust_tier' => TrustTier::Trusted,
                'evidence_strength' => EvidenceStrength::CertifiedSource,
                'direction' => EvidenceDirection::SupportsHalal,
                'review_status' => ReviewStatus::Approved,
                'checked_at' => now(),
                'source_reference' => 'https://authority.example/certificates/THA-API-001',
            ]);

        $this->getJson('/api/products/4780000000036/verdict')
            ->assertOk()
            ->assertJsonPath('data.product.barcode', '4780000000036')
            ->assertJsonPath('data.product.name', 'Verified Yogurt')
            ->assertJsonPath('data.product.brand', 'Test Brand')
            ->assertJsonPath('data.verdict.status', 'halal')
            ->assertJsonPath('data.verdict.has_sufficient_positive_evidence', true)
            ->assertJsonPath('data.verdict.has_blocking_risk', false)
            ->assertJsonPath('data.verdict.reason_codes', []);
    }

    public function test_it_returns_unknown_for_product_without_evidence(): void
    {
        Product::factory()->create([
            'barcode' => '4780000000043',
            'name' => 'Unknown Snack',
        ]);

        $this->getJson('/api/products/4780000000043/verdict')
            ->assertOk()
            ->assertJsonPath('data.product.barcode', '4780000000043')
            ->assertJsonPath('data.verdict.status', 'unknown')
            ->assertJsonPath('data.verdict.reason_codes.0', 'insufficient_evidence');
    }

    public function test_it_returns_unknown_for_unknown_barcode(): void
    {
        $this->getJson('/api/products/9999999999999/verdict')
            ->assertOk()
            ->assertJsonPath('data.product.barcode', '9999999999999')
            ->assertJsonPath('data.product.name', null)
            ->assertJsonPath('data.verdict.status', 'unknown')
            ->assertJsonPath('data.verdict.reason_codes.0', 'unknown_product')
            ->assertJsonPath('data.verdict.has_sufficient_positive_evidence', false)
            ->assertJsonPath('data.verdict.has_blocking_risk', false);
    }
}
