<?php

namespace App\Domain\Halal;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RequiresEditorReview = 'requires_editor_review';
    case RequiresScholarReview = 'requires_scholar_review';
}
