<?php

namespace App\Services\Halal;

use App\Domain\Halal\ReasonCode;
use App\Models\Product;

class ProductVerdictPreview
{
    public function __construct(
        private readonly ProductVerdictService $verdictService,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     reason_codes: string,
     *     has_sufficient_positive_evidence: string,
     *     has_blocking_risk: string
     * }
     */
    public function forProduct(Product $product): array
    {
        $verdict = $this->verdictService->evaluate($product);

        return [
            'status' => $verdict->status->value,
            'reason_codes' => $this->formatReasonCodes($verdict->reasonCodes),
            'has_sufficient_positive_evidence' => $verdict->hasSufficientPositiveEvidence ? 'yes' : 'no',
            'has_blocking_risk' => $verdict->hasBlockingRisk ? 'yes' : 'no',
        ];
    }

    /**
     * @param  list<ReasonCode>  $reasonCodes
     */
    private function formatReasonCodes(array $reasonCodes): string
    {
        if ($reasonCodes === []) {
            return '-';
        }

        return implode(', ', array_map(
            static fn (ReasonCode $reasonCode): string => $reasonCode->value,
            $reasonCodes,
        ));
    }
}
