<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->string('madhhab')->default('hanafi');
            $table->string('public_verdict_effect');
            $table->string('fiqh_classification')->nullable();
            $table->string('reason_code');
            $table->boolean('requires_origin_evidence')->default(false);
            $table->boolean('requires_process_evidence')->default(false);
            $table->boolean('requires_scholar_review')->default(false);
            $table->boolean('requires_editor_review')->default(true);
            $table->string('review_status')->default('pending');
            $table->string('source_reference')->nullable();
            $table->text('explanation_template')->nullable();
            $table->timestamps();

            $table->index(['scope', 'madhhab']);
            $table->index(['review_status', 'requires_scholar_review']);
        });

        Schema::table('evidence', function (Blueprint $table): void {
            $table->foreignId('rule_id')
                ->nullable()
                ->after('ingredient_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::create('user_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('report_type');
            $table->string('review_status')->default('pending');
            $table->string('reason_code')->nullable();
            $table->text('submitted_evidence')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('reporter_contact_hash')->nullable();
            $table->text('reviewer_decision')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reports');

        Schema::table('evidence', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rule_id');
        });

        Schema::dropIfExists('rules');
    }
};
