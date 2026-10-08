<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiAnalyzerUnavailableException;
use App\Exceptions\AiProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeIngredientsRequest;
use App\Services\Ai\GeminiIngredientAnalyzer;
use Illuminate\Http\JsonResponse;

class IngredientAnalysisController extends Controller
{
    public function __invoke(
        AnalyzeIngredientsRequest $request,
        GeminiIngredientAnalyzer $analyzer,
    ): JsonResponse {
        try {
            $analysis = $analyzer->analyze($request->validated());
        } catch (AiAnalyzerUnavailableException) {
            return response()->json([
                'message' => 'AI ingredient analysis is not configured.',
                'code' => 'ai_analyzer_unavailable',
            ], 503);
        } catch (AiProviderException) {
            return response()->json([
                'message' => 'AI ingredient analysis is temporarily unavailable.',
                'code' => 'ai_provider_error',
            ], 502);
        }

        return response()->json(['data' => $analysis]);
    }
}
