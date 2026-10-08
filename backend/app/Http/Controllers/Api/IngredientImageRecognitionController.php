<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiAnalyzerUnavailableException;
use App\Exceptions\AiProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecognizeIngredientImageRequest;
use App\Services\Ai\GeminiIngredientImageRecognizer;
use Illuminate\Http\JsonResponse;

class IngredientImageRecognitionController extends Controller
{
    public function __invoke(
        RecognizeIngredientImageRequest $request,
        GeminiIngredientImageRecognizer $recognizer,
    ): JsonResponse {
        try {
            $recognition = $recognizer->recognize($request->file('image'));
        } catch (AiAnalyzerUnavailableException) {
            return response()->json([
                'message' => 'AI ingredient recognition is not configured.',
                'code' => 'ai_analyzer_unavailable',
            ], 503);
        } catch (AiProviderException) {
            return response()->json([
                'message' => 'AI ingredient recognition is temporarily unavailable.',
                'code' => 'ai_provider_error',
            ], 502);
        }

        return response()->json(['data' => $recognition]);
    }
}
