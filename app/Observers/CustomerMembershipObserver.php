<?php

namespace App\Observers;

use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;

class CustomerMembershipObserver
{
    /**
     * Keep tb_customers.current_membership_id in step after any membership write.
     * Also re-syncs the previous owner on the (unexpected) case of a membership
     * being moved between customers.
     *
     * @param CustomerMembership $membership
     * @return void
     */
    public function saved(CustomerMembership $membership): void
    {
        if ($membership->wasChanged('customer_id')) {
            $this->syncPointer((int) $membership->getOriginal('customer_id'));
        }

        $this->syncPointer((int) $membership->customer_id);
    }

    /**
     * Re-point the customer after a membership is soft or hard deleted.
     *
     * @param CustomerMembership $membership
     * @return void
     */
    public function deleted(CustomerMembership $membership): void
    {
        $this->syncPointer((int) $membership->customer_id);
    }

    /**
     * Re-point the customer after a soft-deleted membership is restored.
     *
     * @param CustomerMembership $membership
     * @return void
     */
    public function restored(CustomerMembership $membership): void
    {
        $this->syncPointer((int) $membership->customer_id);
    }

    /**
     * Re-point the customer after a membership is force deleted.
     *
     * @param CustomerMembership $membership
     * @return void
     */
    public function forceDeleted(CustomerMembership $membership): void
    {
        $this->syncPointer((int) $membership->customer_id);
    }

    /**
     * The single definition of "which membership is a customer's current one":
     * the newest non-deleted membership by start date, then creation date, then
     * id. Written with a query-builder update so it does not fire Customer
     * model events and cannot recurse back into this observer.
     *
     * @param int $customerId
     * @return void
     */
    private function syncPointer(int $customerId): void
    {
        if ($customerId <= 0) {
            return;
        }

        $currentMembershipId = CustomerMembership::where('customer_id', $customerId)
            ->orderByDesc('membership_start_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');

        Customer::withTrashed()
            ->where('id', $customerId)
            ->update(['current_membership_id' => $currentMembershipId]);
    }
}
