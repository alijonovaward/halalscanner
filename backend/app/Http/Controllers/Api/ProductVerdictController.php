<?php

namespace App\Http\Controllers\Api;

use App\Domain\Halal\ReasonCode;
use App\Domain\Halal\VerdictResult;
use App\Domain\Halal\VerdictStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VerdictResultResource;
use App\Models\Product;
use App\Services\Halal\ProductVerdictService;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVerdictController extends Controller
{
    public function __invoke(string $barcode, ProductVerdictService $verdictService): JsonResource
    {
        $product = Product::query()
            ->where('barcode', $barcode)
            ->first();

        if ($product === null) {
            return VerdictResultResource::make([
                'product' => (new Product)->forceFill([
                    'barcode' => $barcode,
                    'name' => null,
                    'brand' => null,
                ]),
                'verdict' => VerdictResult::make(
                    status: VerdictStatus::Unknown,
                    hasSufficientPositiveEvidence: false,
                    hasBlockingRisk: false,
                    reasonCodes: [ReasonCode::UnknownProduct],
                ),
            ]);
        }

        return VerdictResultResource::make([
            'product' => $product,
            'verdict' => $verdictService->evaluate($product),
        ]);
    }
}
