<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Schemas\ProductInfolist;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Tests\TestCase;

class ProductInfolistPreviewTest extends TestCase
{
    public function test_product_infolist_exposes_read_only_verdict_preview_section(): void
    {
        $schema = ProductInfolist::configure(Schema::make());

        $previewSection = collect($schema->getComponents())
            ->first(
                fn (mixed $component): bool => $component instanceof Section
                    && $component->getHeading() === 'Verdict preview',
            );

        $this->assertInstanceOf(Section::class, $previewSection);
    }
}
