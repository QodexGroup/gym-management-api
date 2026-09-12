<?php

namespace App\Http\Controllers\Account\AccountSubscription;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\AccountSubscription\AccountInvoiceResource;
use App\Repositories\Account\AccountSubscription\AccountInvoiceRepository;
use Illuminate\Http\JsonResponse;

/**
 * The signed-in account's own subscription invoices.
 *
 * This surface did not exist before: the owner's "Invoices" tab listed PAYMENT
 * REQUESTS filtered to invoice-linked ones, so a freshly generated invoice with
 * no receipt yet was invisible — and since submitting a receipt needs an
 * invoice id taken from that list, an unpaid invoice could never be paid.
 */
class AccountInvoiceController
{
    public function __construct(
        private AccountInvoiceRepository $invoiceRepository,
    ) {
    }

    /**
     * The account's invoices, newest first, paginated.
     *
     * Goes straight to the repository: this is a plain scoped read with no
     * branching, calculation or side effect, which is where the layering rule
     * allows skipping the service.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function getInvoices(GenericRequest $request): JsonResponse
    {
        $invoices = $this->invoiceRepository->paginateByAccount($request->getGenericData());

        return ApiResponse::success(AccountInvoiceResource::collection($invoices)->response()->getData(true));
    }
}
