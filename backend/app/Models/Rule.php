<?php

namespace App\Models;

use App\Domain\Halal\FiqhClassification;
use App\Domain\Halal\ReasonCode;
use App\Domain\Halal\ReviewStatus;
use App\Domain\Halal\VerdictStatus;
use Database\Factories\RuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'ingredient_id', 'scope', 'madhhab', 'public_verdict_effect', 'fiqh_classification', 'reason_code', 'requires_origin_evidence', 'requires_process_evidence', 'requires_scholar_review', 'requires_editor_review', 'review_status', 'source_reference', 'explanation_template'])]
class Rule extends Model
{
    /** @use HasFactory<RuleFactory> */
    use HasFactory;

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
     * @return HasMany<Evidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'public_verdict_effect' => VerdictStatus::class,
            'fiqh_classification' => FiqhClassification::class,
            'reason_code' => ReasonCode::class,
            'requires_origin_evidence' => 'boolean',
            'requires_process_evidence' => 'boolean',
            'requires_scholar_review' => 'boolean',
            'requires_editor_review' => 'boolean',
            'review_status' => ReviewStatus::class,
        ];
    }
}
