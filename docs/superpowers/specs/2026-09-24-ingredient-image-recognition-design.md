# Ingredient Image Recognition Design

## Goal

Allow a visitor to upload a photograph of an ingredient label, use Gemini to transcribe the ingredient text, review or correct that text, and then submit it through the existing ingredient analysis flow.

## User flow

1. The visitor selects or captures a JPEG, PNG, or WebP image from the “Состав с упаковки” section.
2. The page validates the image type and size, then shows a local preview with actions to replace or remove it.
3. The visitor presses “Распознать состав”.
4. The browser sends the image as multipart form data to a dedicated recognition endpoint.
5. Gemini returns only the transcribed ingredient text and a quality status.
6. The page places the returned text in the existing ingredient textarea and keeps it editable.
7. The visitor reviews or corrects the text and presses the existing “Проверить состав с AI” button.

The recognition request never triggers the halal screening itself. Screening continues to use the reviewed textarea value.

## Interface

The existing ingredient form gains an upload area above the textarea. It supports file selection and mobile camera capture through a file input with `accept="image/jpeg,image/png,image/webp"` and `capture="environment"`.

After selection, the upload area displays a thumbnail, filename, and remove action. The recognition button has an independent loading state so the analysis button remains tied to text analysis. Successful recognition focuses the textarea and shows a short instruction to review the text. Failed recognition leaves the image and existing textarea content unchanged.

## Server architecture

A new `POST /api/ingredient-image-recognitions` endpoint accepts multipart form data with an `image` field. A dedicated form request validates:

- required image file;
- MIME type JPEG, PNG, or WebP;
- maximum size 8 MiB.

A focused `GeminiIngredientImageRecognizer` service converts the validated upload to a base64 inline image part and sends it to the configured Gemini `generateContent` endpoint. The prompt treats visible label content as untrusted data, requests transcription only, preserves uncertain fragments, and forbids adding ingredients that are not visible.

Gemini responds with structured JSON:

```json
{
  "ingredients_text": "Сахар, желатин, ароматизатор",
  "quality": "complete",
  "detected_language": "ru"
}
```

`quality` is one of `complete`, `partial`, or `unreadable`. The server validates the response before returning it to the browser. An unreadable response returns a successful structured result with empty text and allows the interface to explain that another photo is needed.

Images are processed in memory for the request and are not written to application storage. Recognition responses are not cached because the image payload is transient and duplicate image submissions are uncommon.

## Failure handling

- Invalid type or oversized file: return HTTP 422 with the validation message.
- Gemini is not configured: return HTTP 503 with `ai_analyzer_unavailable`.
- Provider connection, HTTP, or malformed response failure: return HTTP 502 with `ai_provider_error`.
- Unreadable label: return HTTP 200 with `quality: unreadable`, empty text, and an interface message asking for a clearer, closer photograph.
- Request throttling: use the existing limit of ten AI requests per minute and show a retry message for HTTP 429.

Provider error details and the API key remain server-side.

## Testing

Feature tests cover upload validation, the Gemini multipart-to-inline-data request, structured response validation, provider failures, and the successful API response. The scanner page test verifies that the file control and recognition endpoint are present.

Browser verification covers desktop and mobile selection states, preview removal, recognized text insertion, editable correction, and the subsequent existing analysis submission. Production build, PHP tests, formatter, JavaScript syntax checks, and console error checks remain required completion gates.
