<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecognizeIngredientImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Добавьте фотографию всей этикетки.',
            'image.uploaded' => 'Изображение не удалось загрузить. Убедитесь, что его размер не превышает 8 МБ.',
            'image.file' => 'Не удалось прочитать выбранное изображение.',
            'image.mimetypes' => 'Поддерживаются изображения JPG, PNG и WebP.',
            'image.max' => 'Размер изображения не должен превышать 8 МБ.',
        ];
    }
}
