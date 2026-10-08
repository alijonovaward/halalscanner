<?php

namespace Tests\Feature\Domain\Halal;

use App\Domain\Halal\EvidenceDirection;
use App\Domain\Halal\EvidenceStrength;
use App\Domain\Halal\ReasonCode;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\SourceType;
use App\Domain\Halal\TrustTier;
use App\Domain\Halal\VerdictStatus;
use App\Models\Certificate;
use App\Models\CertificationBody;
use App\Models\Evidence;
use App\Models\Product;
use App\Models\Rule;
use App\Models\UserReport;
use App\Services\Halal\ProductVerdictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVerdictServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_without_evidence_returns_unknown(): void
    {
        $result = app(ProductVerdictService::class)->evaluate(Product::factory()->create());

        $this->assertSame(VerdictStatus::Unknown, $result->status);
        $this->assertContains(ReasonCode::InsufficientEvidence, $result->reasonCodes);
    }

    public function test_trusted_approved_certificate_evidence_can_return_halal(): void
    {
        $product = Product::factory()->create();
        $certificate = $this->createTrustedCertificate($product);

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
                'source_reference' => 'https://authority.example/certificates/THA-001',
            ]);

        $result = app(ProductVerdictService::class)->evaluate($product);

        $this->assertSame(VerdictStatus::Halal, $result->status);
        $this->assertTrue($result->hasSufficientPositiveEvidence);
        $this->assertFalse($result->hasBlockingRisk);
    }

    public function test_expired_certificate_evidence_cannot_return_halal(): void
    {
        $product = Product::factory()->create();
        $certificate = $this->createTrustedCertificate($product, [
            'valid_until' => now()->subDay()->toDateString(),
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
                'source_reference' => 'https://authority.example/certificates/THA-EXPIRED',
            ]);

        $result = app(ProductVerdictService::class)->evaluate($product);

        $this->assertSame(VerdictStatus::Unknown, $result->status);
        $this->assertFalse($result->hasSufficientPositiveEvidence);
        $this->assertContains(ReasonCode::InsufficientEvidence, $result->reasonCodes);
    }

    public function test_non_product_certificate_scope_cannot_return_halal(): void
    {
        $product = Product::factory()->create();
        $certificate = $this->createTrustedCertificate($product, [
            'scope' => 'facility',
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
                'source_reference' => 'https://authority.example/certificates/THA-FACILITY',
            ]);

        $result = app(ProductVerdictService::class)->evaluate($product);

        $this->assertSame(VerdictStatus::Unknown, $result->status);
        $this->assertFalse($result->hasSufficientPositiveEvidence);
        $this->assertContains(ReasonCode::InsufficientEvidence, $result->reasonCodes);
    }

    public function test_non_valid_certificate_status_cannot_return_halal(): void
    {
        $product = Product::factory()->create();
        $certificate = $this->createTrustedCertificate($product, [
            'status' => 'revoked',
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
                'source_reference' => 'https://authority.example/certificates/THA-REVOKED',
            ]);

        $result = app(ProductVerdictService::class)->evaluate($product);

        $this->assertSame(VerdictStatus::Unknown, $result->status);
        $this->assertFalse($result->hasSufficientPositiveEvidence);
        $this->assertContains(ReasonCode::InsufficientEvidence, $result->reasonCodes);
    }

    public function test_unverified_certificate_cannot_return_halal(): void
    {
        $product = Product::factory()->create();
        $certificate = $this->createTrustedCertificate($product, [
            'verified_at' => null,
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
                'source_reference' => 'https://authority.example/certificates/THA-UNVERIFIED',
            ]);

        $result = app(ProductVerdictService::class)->evaluate($product);

        $this->assertSame(VerdictStatus::Unknown, $result->status);
        $this->assertFalse($result->hasSufficientPositiveEvidence);
        $this->assertContains(ReasonCode::InsufficientEvidence, $result->reasonCodes);
    }

    public function test_not_yet_valid_certificate_cannot_return_halal(): void
    {
        $product = Product::factory()->create();
        $certificate = $this->createTrustedCertificate($product, [
            'valid_from' => now()->addDay()->toDateString(),
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
                'source_reference' => 'https://authority.example/certificates/THA-FUTURE',
            ]);

        $result = app(ProductVerdictService::class)->evaluate($product);

        $this->assertSame(VerdictStatus::Unknown, $result->status);
        $this->assertFalse($result->hasSufficientPositiveEvidence);
        $this->assertContains(ReasonCode::InsufficientEvidence, $result->reasonCodes);
    }

    public function test_certificate_for_another_product_cannot_return_halal(): void
    {
        $product = Product::factory()->create();
        $certificate = $this->createTrustedCertificate(Product::factory()->create());

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
                'source_reference' => 'https://authority.example/certificates/THA-OTHER-PRODUCT',
            ]);

        $result = app(ProductVerdictService::class)->evaluate($product);

        $this->assertSame(VerdictStatus::Unknown, $result->status);
        $this->assertFalse($result->hasSufficientPositiveEvidence);
        $this->assertContains(ReasonCode::InsufficientEvidence, $result->reasonCodes);
    }

    public function test_unverified_package_halal_claim_returns_mushbooh(): void
    {
        $product = Product::factory()->create();

        Evidence::factory()
            ->for($product)
            ->create([
                'source_type' => SourceType::PackageHalalClaim,
                'trust_tier' => TrustTier::Unreviewed,
                'evidence_strength' => EvidenceStrength::ManufacturerClaim,
                'direction' => EvidenceDirection::SupportsHalal,
                'review_status' => ReviewStatus::Pending,
            ]);

        $result = app(ProductVerdictService::class)->evaluate($product);

        $this->assertSame(VerdictStatus::Mushbooh, $result->status);
        $this->assertContains(ReasonCode::CertificateMissing, $result->reasonCodes);
    }

    public function test_pending_user_report_only_does_not_create_halal_verdict(): void
    {
        $product = Product::factory()->create();

        UserReport::factory()
            ->for($product)
            ->create([
                'review_status' => ReviewStatus::Pending,
                'submitted_evidence' => 'The package says halal.',
            ]);

        $result = app(ProductVerdictService::class)->evaluate($product);

        $this->assertSame(VerdictStatus::Unknown, $result->status);
        $this->assertContains(ReasonCode::InsufficientEvidence, $result->reasonCodes);
    }

    public function test_trusted_positive_and_blocking_evidence_returns_mushbooh(): void
    {
        $product = Product::factory()->create();
        $certificate = $this->createTrustedCertificate($product);

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
                'source_reference' => 'https://authority.example/certificates/THA-002',
            ]);

        $rule = Rule::factory()
            ->for($product)
            ->create([
                'source_reference' => 'https://scholars.example/rules/pork-enzyme',
                'review_status' => ReviewStatus::Approved,
            ]);

        Evidence::factory()
            ->for($product)
            ->for($rule)
            ->create([
                'source_type' => SourceType::ExpertRule,
                'trust_tier' => TrustTier::Trusted,
                'evidence_strength' => EvidenceStrength::ExpertFatwa,
                'direction' => EvidenceDirection::BlocksHalal,
                'review_status' => ReviewStatus::Approved,
                'checked_at' => now(),
                'source_reference' => 'https://scholars.example/rules/pork-enzyme',
            ]);

        $result = app(ProductVerdictService::class)->evaluate($product);

        $this->assertSame(VerdictStatus::Mushbooh, $result->status);
        $this->assertContains(ReasonCode::SourceConflict, $result->reasonCodes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTrustedCertificate(Product $product, array $attributes = []): Certificate
    {
        $body = CertificationBody::factory()->create([
            'trust_status' => 'trusted',
        ]);

        return Certificate::factory()
            ->for($product)
            ->for($body)
            ->create(array_merge([
                'certificate_number' => 'THA-001',
                'status' => 'valid',
                'verified_at' => now(),
            ], $attributes));
    }
}
