<?php

namespace Tests\Feature\AccountSubscription;

use App\Constant\AccountPaymentRequestStatusConstant;
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Models\Account\AccountInvoice;

class AccountPaymentRequestTest extends AccountSubscriptionFlowTestCase
{
    public function test_create_payment_request_for_invoice(): void
    {
        $account = $this->createAccount();
        $user = $this->createUser($account);
        $plan = $this->createPlan(['interval' => AccountSubscriptionIntervalConstant::INTERVAL_MONTH]);
        $asp = $this->createAccountSubscriptionPlan($account, $plan);
        $invoice = $this->createInvoice($account, $asp);

        $genericData = $this->buildPaymentRequestGenericData($user, $invoice, [
            'receiptUrl' => 'receipts/invoices/receipt-123.png',
            'receiptFileName' => 'receipt-123.png',
        ]);

        $request = $this->accountPaymentRequestService->createInvoicePaymentRequest($genericData);

        $this->assertSame(AccountPaymentRequestStatusConstant::STATUS_PENDING, $request->status);
        $this->assertSame($account->id, $request->account_id);
        $this->assertSame(AccountInvoice::class, $request->payment_transaction);
        $this->assertSame($invoice->id, $request->payment_transaction_id);
        $this->assertSame($user->id, $request->requested_by);
        $this->assertSame('receipts/invoices/receipt-123.png', $request->receipt_url);

        $details = $this->decodeJson($request->payment_details);
        $this->assertSame($invoice->invoice_number, $details['invoice_number'] ?? null);
        $this->assertSame((float) $invoice->total_amount, (float) ($details['total_amount'] ?? 0));
    }
}
