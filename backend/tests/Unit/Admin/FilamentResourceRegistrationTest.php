<?php

namespace Tests\Unit\Admin;

use App\Filament\Resources\Certificates\CertificateResource;
use App\Filament\Resources\CertificationBodies\CertificationBodyResource;
use App\Filament\Resources\Evidence\EvidenceResource;
use App\Filament\Resources\Ingredients\IngredientResource;
use App\Filament\Resources\ProductCandidates\ProductCandidateResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Rules\RuleResource;
use App\Filament\Resources\UserReports\UserReportResource;
use App\Models\Certificate;
use App\Models\CertificationBody;
use App\Models\Evidence;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCandidate;
use App\Models\Rule;
use App\Models\UserReport;
use Filament\Resources\Resource;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FilamentResourceRegistrationTest extends TestCase
{
    public function test_mvp_admin_resources_are_registered_for_their_models(): void
    {
        $resources = [
            ProductResource::class => Product::class,
            ProductCandidateResource::class => ProductCandidate::class,
            IngredientResource::class => Ingredient::class,
            RuleResource::class => Rule::class,
            EvidenceResource::class => Evidence::class,
            CertificateResource::class => Certificate::class,
            CertificationBodyResource::class => CertificationBody::class,
            UserReportResource::class => UserReport::class,
        ];

        foreach ($resources as $resourceClass => $modelClass) {
            $this->assertTrue(class_exists($resourceClass), "{$resourceClass} is missing.");
            $this->assertTrue(is_a($resourceClass, Resource::class, true), "{$resourceClass} must be a Filament resource.");
            $this->assertSame($modelClass, $resourceClass::getModel());
        }
    }

    public function test_filament_resources_do_not_contain_halal_rule_engine_logic(): void
    {
        $resourcesPath = dirname(__DIR__, 3).'/app/Filament/Resources';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resourcesPath));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            $this->assertStringNotContainsString('App\\Domain\\Halal', $contents, $file->getPathname());
            $this->assertStringNotContainsString('VerdictCalculator', $contents, $file->getPathname());
            $this->assertStringNotContainsString('RuleEngine', $contents, $file->getPathname());
        }
    }

    public function test_product_candidates_can_only_enter_the_review_queue_through_import(): void
    {
        $pages = ProductCandidateResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayHasKey('edit', $pages);
        $this->assertArrayNotHasKey('create', $pages);
    }
}
