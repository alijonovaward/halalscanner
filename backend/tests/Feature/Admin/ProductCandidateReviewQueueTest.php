<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ProductCandidates\ProductCandidateResource;
use App\Models\ProductCandidate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCandidateReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_can_open_candidate_list_view_and_edit_pages(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $candidate = ProductCandidate::factory()->create();

        $this->get(ProductCandidateResource::getUrl('index'))->assertOk();
        $this->get(ProductCandidateResource::getUrl('view', ['record' => $candidate]))->assertOk();
        $this->get(ProductCandidateResource::getUrl('edit', ['record' => $candidate]))->assertOk();
    }

    public function test_regular_user_cannot_open_the_candidate_review_queue(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get(ProductCandidateResource::getUrl('index'))->assertForbidden();
    }
}
