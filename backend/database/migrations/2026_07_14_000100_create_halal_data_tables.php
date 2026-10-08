<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('barcode')->nullable()->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->text('ingredients_text')->nullable();
            $table->timestamps();
        });

        Schema::create('ingredients', function (Blueprint $table): void {
            $table->id();
            $table->string('canonical_name');
            $table->string('slug')->unique();
            $table->string('origin')->default('unknown');
            $table->boolean('requires_origin_evidence')->default(false);
            $table->boolean('requires_process_evidence')->default(false);
            $table->timestamps();
        });

        Schema::create('product_ingredient', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('declared_name')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });

        Schema::create('certification_bodies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('country_code', 2)->nullable();
            $table->string('trust_status')->default('unknown');
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_body_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_number');
            $table->string('scope')->default('product');
            $table->string('status')->default('unknown');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('source_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'valid_until']);
        });

        Schema::create('evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type');
            $table->string('trust_tier')->default('unreviewed');
            $table->string('evidence_strength')->default('inferred');
            $table->string('direction');
            $table->string('review_status')->default('pending');
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'trust_tier']);
            $table->index(['direction', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certification_bodies');
        Schema::dropIfExists('product_ingredient');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('products');
    }
};
