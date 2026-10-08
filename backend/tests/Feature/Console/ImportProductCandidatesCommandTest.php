<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Models\ProductCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImportProductCandidatesCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            @unlink($temporaryFile);
        }

        parent::tearDown();
    }

    public function test_product_candidate_import_command_is_registered(): void
    {
        $this->assertArrayHasKey('catalog:import-candidates', Artisan::all());
    }

    public function test_it_imports_candidates_without_creating_catalog_products(): void
    {
        $path = $this->writeCsv([
            $this->candidateRow(
                barcode: '4780042470202',
                name: 'Молоко Lactel 1%',
                brand: 'Lactel',
                ingredients: 'Молоко коровье нормализованное',
            ),
            $this->candidateRow(
                barcode: '4780012960177',
                name: 'Hydrolife Eco',
                brand: 'Hydrolife',
                ingredients: 'Природная питьевая вода',
            ),
        ]);

        $this->artisan('catalog:import-candidates', ['path' => $path])
            ->expectsOutputToContain('Imported 2 product candidates.')
            ->assertExitCode(0);

        $this->assertSame(2, ProductCandidate::query()->count());
        $this->assertSame(0, Product::query()->count());

        $candidate = ProductCandidate::query()
            ->where('barcode', '4780042470202')
            ->firstOrFail();

        $this->assertSame('Молоко Lactel 1%', $candidate->name);
        $this->assertSame('Lactel', $candidate->brand);
        $this->assertSame(['en:uzbekistan'], $candidate->market_tags);
        $this->assertSame('pending_review', $candidate->review_status);
        $this->assertSame(['no_ingredients_image_in_source'], $candidate->data_quality_flags);
    }

    public function test_it_rejects_an_invalid_barcode_without_importing_any_rows(): void
    {
        $path = $this->writeCsv([
            $this->candidateRow(
                barcode: '4780042470202',
                name: 'Молоко Lactel 1%',
                brand: 'Lactel',
                ingredients: 'Молоко коровье нормализованное',
            ),
            $this->candidateRow(
                barcode: '4780042470203',
                name: 'Invalid barcode product',
                brand: 'Invalid',
                ingredients: '',
            ),
        ]);

        $this->artisan('catalog:import-candidates', ['path' => $path])
            ->expectsOutputToContain('Invalid GTIN barcode: 4780042470203')
            ->assertExitCode(1);

        $this->assertSame(0, ProductCandidate::query()->count());
    }

    public function test_it_rejects_external_halal_conclusions(): void
    {
        $row = $this->candidateRow(
            barcode: '4780042470202',
            name: 'Молоко Lactel 1%',
            brand: 'Lactel',
            ingredients: 'Молоко коровье нормализованное',
        );
        $row['halal_status'] = 'halal';
        $path = $this->writeCsv([$row]);

        $this->artisan('catalog:import-candidates', ['path' => $path])
            ->expectsOutputToContain('Candidate rows must not contain a halal conclusion.')
            ->assertExitCode(1);

        $this->assertSame(0, ProductCandidate::query()->count());
        $this->assertSame(0, Product::query()->count());
    }

    public function test_it_does_not_overwrite_a_reviewed_candidate(): void
    {
        $candidate = ProductCandidate::factory()->create([
            'barcode' => '4780042470202',
            'name' => 'Проверенное название',
            'review_status' => 'verified',
        ]);
        $path = $this->writeCsv([
            $this->candidateRow(
                barcode: '4780042470202',
                name: 'Непроверенное название источника',
                brand: 'Lactel',
                ingredients: 'Молоко коровье нормализованное',
            ),
        ]);

        $this->artisan('catalog:import-candidates', ['path' => $path])
            ->expectsOutputToContain('Imported 0 product candidates.')
            ->expectsOutputToContain('Skipped 1 reviewed product candidate.')
            ->assertExitCode(0);

        $candidate->refresh();

        $this->assertSame('Проверенное название', $candidate->name);
        $this->assertSame('verified', $candidate->review_status);
    }

    public function test_it_reports_missing_required_columns(): void
    {
        $row = $this->candidateRow(
            barcode: '4780042470202',
            name: 'Молоко Lactel 1%',
            brand: 'Lactel',
            ingredients: 'Молоко коровье нормализованное',
        );
        unset($row['source_license']);
        $path = $this->writeCsv([$row]);

        $this->artisan('catalog:import-candidates', ['path' => $path])
            ->expectsOutputToContain('Candidate CSV is missing required columns: source_license')
            ->assertExitCode(1);

        $this->assertSame(0, ProductCandidate::query()->count());
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'product-candidates-');

        if ($path === false) {
            $this->fail('Unable to create a temporary CSV file.');
        }

        $stream = fopen($path, 'wb');

        if ($stream === false) {
            $this->fail('Unable to open the temporary CSV file.');
        }

        fputcsv($stream, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        fclose($stream);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @return array<string, string>
     */
    private function candidateRow(string $barcode, string $name, string $brand, string $ingredients): array
    {
        return [
            'barcode' => $barcode,
            'name_raw' => $name,
            'brand_raw' => $brand,
            'ingredients_text_raw' => $ingredients,
            'source_url' => "https://world.openfoodfacts.org/product/{$barcode}",
            'source_modified_at_utc' => '2026-09-18T12:00:00+00:00',
            'source_dataset' => 'Open Food Facts',
            'source_license' => 'ODbL-1.0',
            'country_tag' => 'en:uzbekistan',
            'catalog_review_status' => 'pending_review',
            'halal_status' => 'unknown',
            'data_quality_flags' => 'no_ingredients_image_in_source',
        ];
    }
}
