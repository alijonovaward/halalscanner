<?php

namespace Tests\Feature\Database;

use App\Domain\Halal\EvidenceDirection;
use App\Domain\Halal\EvidenceStrength;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\SourceType;
use App\Domain\Halal\TrustTier;
use App\Models\Certificate;
use App\Models\CertificationBody;
use App\Models\Evidence;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HalalDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_connects_ingredients_certificates_and_evidence(): void
    {
        $product = Product::factory()->create([
            'barcode' => '4780000000012',
            'name' => 'Test Yogurt',
        ]);

        $ingredient = Ingredient::factory()->create([
            'canonical_name' => 'Gelatin',
            'origin' => 'unknown',
            'requires_origin_evidence' => true,
        ]);

        $product->ingredients()->attach($ingredient, [
            'declared_name' => 'gelatin',
            'position' => 1,
        ]);

        $certificationBody = CertificationBody::factory()->create([
            'name' => 'Trusted Halal Authority',
            'trust_status' => 'trusted',
        ]);

        $certificate = Certificate::factory()
            ->for($product)
            ->for($certificationBody)
            ->create([
                'certificate_number' => 'THA-2026-001',
                'scope' => 'product',
                'status' => 'valid',
            ]);

        $evidence = Evidence::factory()
            ->for($product)
            ->for($certificate)
            ->create([
                'source_type' => SourceType::Certificate,
                'trust_tier' => TrustTier::Trusted,
                'evidence_strength' => EvidenceStrength::CertifiedSource,
                'direction' => EvidenceDirection::SupportsHalal,
                'review_status' => ReviewStatus::Approved,
            ]);

        $this->assertTrue($product->ingredients()->whereKey($ingredient)->exists());
        $this->assertTrue($product->certificates()->whereKey($certificate)->exists());
        $this->assertTrue($product->evidence()->whereKey($evidence)->exists());
        $this->assertSame('trusted', $certificate->certificationBody->trust_status);
    }
}
