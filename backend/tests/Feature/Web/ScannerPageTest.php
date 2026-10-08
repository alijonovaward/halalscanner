<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class ScannerPageTest extends TestCase
{
    public function test_scanner_page_exposes_catalog_and_ai_analysis_workflow(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('HalalScan')
            ->assertSee('Проверьте продукт')
            ->assertSee('Штрихкод')
            ->assertSee('Сканировать камерой')
            ->assertSee('id="barcode-camera-input"', false)
            ->assertSee('id="barcode-scanner"', false)
            ->assertSee('id="barcode-scanner-video"', false)
            ->assertSee('Текст всей этикетки')
            ->assertSee('Загрузить фото всей этикетки')
            ->assertSee('Снять фото')
            ->assertSee('Выбрать из галереи')
            ->assertSee('Большие фото уменьшим автоматически')
            ->assertSee('Определим товар, прочитаем этикетку и проверим её автоматически')
            ->assertSee('id="ingredient-camera"', false)
            ->assertSee('capture="environment"', false)
            ->assertSee('accept="image/jpeg,image/png,image/webp"', false)
            ->assertSee('Проверьте распознанный текст всей этикетки')
            ->assertSee('Проверить исправленный текст')
            ->assertSee('Предварительный AI-разбор. Это не halal-вердикт.')
            ->assertSee(route('api.products.verdict', ['barcode' => '__BARCODE__']), false)
            ->assertSee(route('api.ingredient-image-recognitions.store'), false)
            ->assertSee(route('api.ingredient-analyses.store'), false);
    }
}
