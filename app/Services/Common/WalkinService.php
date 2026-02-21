<?php

namespace App\Services\Common;

use App\Helpers\GenericData;
use App\Models\Common\WalkinCustomer;
use App\Repositories\Common\WalkinRepository;
use Illuminate\Support\Facades\Log;

class WalkinService
{
    protected WalkinRepository $walkinRepository;

    public function __construct(WalkinRepository $walkinRepository)
    {
        $this->walkinRepository = $walkinRepository;
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
}
