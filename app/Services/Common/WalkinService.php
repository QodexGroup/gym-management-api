<?php

namespace App\Services\Common;

use App\Constants\WalkinCustomerConstant;
use App\Helpers\GenericData;
use App\Models\Common\Walkin;
use App\Models\Common\WalkinCustomer;
use App\Models\Core\Customer;
use App\Repositories\Common\WalkinRepository;
use App\Repositories\Core\CustomerRepository;
use Illuminate\Support\Facades\Log;

class WalkinService
{
    protected WalkinRepository $walkinRepository;
    protected CustomerRepository $customerRepository;

    public function __construct(WalkinRepository $walkinRepository, CustomerRepository $customerRepository)
    {
        $this->walkinRepository = $walkinRepository;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param int $walkinId
     * @param GenericData $genericData
     *
     * @return WalkinCustomer
     */
    public function createWalkinCustomer(int $walkinId, GenericData $genericData): WalkinCustomer
    {
       try {
            $walkin = $this->walkinRepository->getWalkin($genericData);
            if (!$walkin) {
                throw new \Exception('Walkin not found');
            }
            // check if walkin customer already exists
            $walkinCustomer = $this->walkinRepository->getWalkinCustomer($walkinId, $genericData);

            if ($walkinCustomer) {
                throw new \Exception('Walkin customer already exists');
            }
            // create walkin customer
            $walkinCustomer = $this->walkinRepository->createWalkinCustomer($walkinId, $genericData);
            return $walkinCustomer;

        } catch (\Throwable $th) {
            Log::error('Error creating walkin customer: ' . $th->getMessage());
            throw $th;
        }
    }

    /**
     * QR Code Check-in (convenience method for kiosk)
     * Gets customer by UUID, gets or creates today's walk-in, and checks in the customer
     *
     * @param GenericData $genericData
     * @return WalkinCustomer
     * @throws \Exception
     */
    public function qrCheckIn(GenericData $genericData): WalkinCustomer
    {
        try {
            $customer = $this->customerRepository->findCustomerByUuid($genericData->getData()->uuid, $genericData->userData->account_id);
            if (!$customer) {
                throw new \Exception('Customer not found');
            }

            // Set customer_id in genericData so repository lookups (e.g. duplicate check) can work.
            $genericData->getData()->customerId = $customer->id;
            $genericData->syncDataArray();
            // Get or create today's walk-in
            $walkin = $this->walkinRepository->getWalkin($genericData);

            if (!$walkin) {
                $walkin = $this->walkinRepository->createWalkin($genericData);
            }

            // Check if customer is already checked in today
            $existingWalkinCustomer = $this->walkinRepository->getWalkinCustomer(
                $walkin->id,
                $genericData
            );

            if ($existingWalkinCustomer) {
                throw new \Exception('Customer is already checked in');
            }

            // Check in customer
            $walkinCustomer = $this->walkinRepository->createWalkinCustomer(
                $walkin->id,
                $genericData
            );

            return $walkinCustomer;
        } catch (\Throwable $th) {
            Log::error('Error in QR check-in: ' . $th->getMessage());
            throw $th;
        }
    }

    /**
     * QR Code Check-out (convenience method for kiosk)
     * Gets customer by UUID, finds today's walk-in, and checks out the customer
     *
     * @param GenericData $genericData
     * @return WalkinCustomer
     * @throws \Exception
     */
    public function qrCheckOut(GenericData $genericData): WalkinCustomer
    {
        try {
            // Get customer by UUID
            $customer = $this->customerRepository->findCustomerByUuid(
                $genericData->getData()->uuid,
                $genericData->userData->account_id
            );

            if (!$customer) {
                throw new \Exception('Customer not found');
            }

            // Get today's walk-in
            $walkin = $this->walkinRepository->getWalkin($genericData);

            if (!$walkin) {
                throw new \Exception('No walk-in session found for today');
            }

            // Set customer_id in genericData to find the walk-in customer
            $genericData->getData()->customerId = $customer->id;
            $genericData->syncDataArray();

            // Find the walk-in customer record
            $walkinCustomer = $this->walkinRepository->getWalkinCustomer(
                $walkin->id,
                $genericData
            );

            if (!$walkinCustomer) {
                throw new \Exception('Customer is not checked in');
            }

            // Check if already checked out
            if ($walkinCustomer->status !== WalkinCustomerConstant::INSIDE_STATUS) {
                throw new \Exception('Customer is already checked out or cancelled');
            }

            // Check out the customer
            $walkinCustomer = $this->walkinRepository->checkOutWalkinCustomer(
                $walkinCustomer->id,
                $genericData
            );

            return $walkinCustomer;
        } catch (\Throwable $th) {
            Log::error('Error in QR check-out: ' . $th->getMessage());
            throw $th;
        }
    }
}
