<?php

namespace App\Http\Controllers\Platform;

use App\Helpers\PlatformResponse;
use App\Http\Requests\Platform\PlatformInvoiceIndexRequest;
use App\Http\Resources\Platform\PlatformInvoiceResource;
use App\Services\Platform\PlatformInvoiceService;
use Illuminate\Http\JsonResponse;

/**
 * Unpaid and overdue invoices across every account.
 */
class PlatformInvoiceController
{
    public function __construct(
        private PlatformInvoiceService $invoiceService,
    ) {
    }

    /**
     * @param PlatformInvoiceIndexRequest $request
     *
     * @return JsonResponse
     */
    public function getInvoices(PlatformInvoiceIndexRequest $request): JsonResponse
    {
        $invoices = $this->invoiceService->getInvoices($request->filters(), $request->page(), $request->limit());

        return PlatformResponse::paginated($invoices, PlatformInvoiceResource::class);
    }
}
