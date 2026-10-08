<?php

namespace Tests\Feature\Domain\Halal;

use App\Domain\Halal\EvidenceDirection;
use App\Domain\Halal\EvidenceStrength;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\SourceType;
use App\Domain\Halal\TrustTier;
use App\Models\Certificate;
use App\Models\CertificationBody;
use App\Models\Evidence;
use App\Models\Product;
use App\Services\Halal\ProductVerdictPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVerdictPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_presents_a_product_verdict_for_filament_read_only_preview(): void
    {
        $product = Product::factory()->create([
            'barcode' => '4780000000050',
            'name' => 'Preview Yogurt',
        ]);

        $body = CertificationBody::factory()->create([
            'trust_status' => 'trusted',
        ]);

        $certificate = Certificate::factory()
            ->for($product)
            ->for($body)
            ->create([
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
                'source_reference' => 'https://authority.example/certificates/preview',
            ]);

        $preview = app(ProductVerdictPreview::class)->forProduct($product);

        $this->assertSame([
            'status' => 'halal',
            'reason_codes' => '-',
            'has_sufficient_positive_evidence' => 'yes',
            'has_blocking_risk' => 'no',
        ], $preview);
    }

    public function test_it_presents_reason_codes_for_cautious_verdicts(): void
    {
        $product = Product::factory()->create();

        $preview = app(ProductVerdictPreview::class)->forProduct($product);

        $this->assertSame('unknown', $preview['status']);
        $this->assertSame('insufficient_evidence', $preview['reason_codes']);
        $this->assertSame('no', $preview['has_sufficient_positive_evidence']);
    }
}
