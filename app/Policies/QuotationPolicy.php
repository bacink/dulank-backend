<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function view(User $user, Quotation $quotation): bool
    {
        return $quotation->user_id === $user->id;
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $quotation->user_id === $user->id;
    }
}
