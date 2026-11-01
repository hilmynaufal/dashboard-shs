<?php

namespace App\Policies;

use App\Authorization\ResourceAccess;
use App\Models\{ItemReview, User};

class ItemReviewPolicy
{
    public function __construct(private ResourceAccess $access) {}

    public function view(User $user, ItemReview $review): bool
    {
        return $this->access->review($user, $review);
    }

    public function update(User $user, ItemReview $review): bool
    {
        return $this->access->writeReview($user, $review);
    }
}
