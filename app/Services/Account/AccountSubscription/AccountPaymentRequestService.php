<?php

namespace App\Services\Account\AccountSubscription;

use App\Constant\AccountInvoiceStatusConstant;
use App\Helpers\GenericData;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountPaymentRequest;
use App\Repositories\Account\AccountSubscription\AccountInvoiceRepository;
use App\Repositories\Account\AccountSubscription\AccountPaymentRequestRepository;
use Illuminate\Support\Facades\DB;

class AccountPaymentRequestService
{
    public function __construct(
        private AccountPaymentRequestRepository $requestRepository,
        private AccountInvoiceRepository $invoiceRepository,
    ) {
    }

    /**
     * Create payment request for an invoice.
     *
     * @param GenericData $genericData
     *
     * @return AccountPaymentRequest
     */
    public function createInvoicePaymentRequest(GenericData $genericData): AccountPaymentRequest
    {
        return DB::transaction(function () use ($genericData) {
            $data = $genericData->getData();
            $accountId = $genericData->userData->account_id;
            $invoiceId = (int) $data->invoiceId;

            $invoice = $this->invoiceRepository->findByIdWithRelations($invoiceId);
            if (!$invoice) {
                throw new \Exception('Invoice not found.');
            }

            if ($invoice->account_id !== $accountId) {
                throw new \Exception('Invoice does not belong to your account.');
            }

            if ($invoice->status === AccountInvoiceStatusConstant::STATUS_PAID) {
                throw new \Exception('Invoice is already paid.');
            }

            if ($this->requestRepository->hasPendingForAccount($accountId, AccountInvoice::class, $invoiceId)) {
                throw new \Exception('You already have a pending payment request for this invoice. Please wait for approval.');
            }

            // Receipt file is uploaded and stored by the frontend (e.g. Firebase).
            $request = $this->requestRepository->createInvoicePaymentRequest($genericData, $invoice);

            return $request->load(['account', 'paymentTransaction']);
        });
    }

}
