<?php

namespace App\Models;

use Database\Factories\ProductCandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['barcode', 'name', 'brand', 'ingredients_text', 'source_url', 'source_modified_at', 'source_dataset', 'source_license', 'market_tags', 'review_status', 'data_quality_flags'])]
class ProductCandidate extends Model
{
    /** @use HasFactory<ProductCandidateFactory> */
    use HasFactory;

    protected $attributes = [
        'review_status' => 'pending_review',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_modified_at' => 'datetime',
            'market_tags' => 'array',
            'data_quality_flags' => 'array',
        ];
    }
}
