<?php

namespace App\Models;

use App\Domain\Halal\EvidenceDirection;
use App\Domain\Halal\EvidenceStrength;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\SourceType;
use App\Domain\Halal\TrustTier;
use Database\Factories\EvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['product_id', 'ingredient_id', 'rule_id', 'certificate_id', 'source_type', 'trust_tier', 'evidence_strength', 'direction', 'review_status', 'checked_at', 'expires_at', 'source_reference', 'notes'])]
class Evidence extends Model
{
    /** @use HasFactory<EvidenceFactory> */
    use HasFactory;

    protected $table = 'evidence';

    protected static function booted(): void
    {
        static::saving(function (self $evidence): void {
            $evidence->guardSourceMetadata();
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Ingredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * @return BelongsTo<Rule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    /**
     * @return BelongsTo<Certificate, $this>
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'trust_tier' => TrustTier::class,
            'evidence_strength' => EvidenceStrength::class,
            'direction' => EvidenceDirection::class,
            'review_status' => ReviewStatus::class,
            'checked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    private function guardSourceMetadata(): void
    {
        if (
            $this->source_type === SourceType::UserReport
            && ($this->trust_tier === TrustTier::Trusted || $this->review_status === ReviewStatus::Approved)
        ) {
            throw new InvalidArgumentException('User report evidence cannot be marked trusted or approved.');
        }

        if (
            $this->trust_tier === TrustTier::Trusted
            && $this->review_status === ReviewStatus::Approved
            && ($this->checked_at === null || blank($this->source_reference))
        ) {
            throw new InvalidArgumentException('Approved trusted evidence requires checked_at and source_reference.');
        }
    }
}
