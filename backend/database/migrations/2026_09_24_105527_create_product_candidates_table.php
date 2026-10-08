<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('barcode')->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->text('ingredients_text')->nullable();
            $table->string('source_url');
            $table->timestamp('source_modified_at')->nullable();
            $table->string('source_dataset');
            $table->string('source_license');
            $table->json('market_tags')->nullable();
            $table->string('review_status')->default('pending_review');
            $table->json('data_quality_flags')->nullable();
            $table->timestamps();

            $table->index(['review_status', 'source_dataset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_candidates');
    }
};
