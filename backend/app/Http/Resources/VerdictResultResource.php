<?php

namespace App\Http\Resources;

use App\Domain\Halal\ReasonCode;
use App\Domain\Halal\VerdictResult;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerdictResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource['product'];

        /** @var VerdictResult $verdict */
        $verdict = $this->resource['verdict'];

        return [
            'product' => [
                'barcode' => $product->barcode,
                'name' => $product->name,
                'brand' => $product->brand,
            ],
            'verdict' => [
                'status' => $verdict->status->value,
                'reason_codes' => array_map(
                    static fn (ReasonCode $reasonCode): string => $reasonCode->value,
                    $verdict->reasonCodes,
                ),
                'has_sufficient_positive_evidence' => $verdict->hasSufficientPositiveEvidence,
                'has_blocking_risk' => $verdict->hasBlockingRisk,
            ],
        ];
    }
}
