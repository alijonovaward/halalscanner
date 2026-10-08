<?php

namespace App\Models;

use App\Domain\Halal\ReviewStatus;
use Database\Factories\UserReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'ingredient_id', 'report_type', 'review_status', 'reason_code', 'submitted_evidence', 'source_reference', 'reporter_contact_hash', 'reviewer_decision', 'reviewed_at'])]
class UserReport extends Model
{
    /** @use HasFactory<UserReportFactory> */
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review_status' => ReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
