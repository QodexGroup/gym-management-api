<?php

namespace App\Repositories\Admin;

use App\Constant\AccountPaymentRequestStatusConstant;
use App\Models\Account\AccountPaymentRequest;
use Illuminate\Database\Eloquent\Collection;

class AdminPaymentRequestRepository
{
    /**
     * @return Collection<int, AccountPaymentRequest>
     */
    public function getPendingPaymentRequests(): Collection
    {
        return AccountPaymentRequest::where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

