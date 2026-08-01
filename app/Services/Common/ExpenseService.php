<?php

namespace App\Services\Common;

use App\Helpers\GenericData;
use App\Models\Common\Expense;
use App\Repositories\Common\ExpenseRepository;
use App\Services\Core\StorageService;

/**
 * Expense business logic. Wraps the repository to keep the account's storage
 * usage in step with the optional receipt file (counted, replaced, released).
 */
class ExpenseService
{
    /**
     * @param ExpenseRepository $expenseRepository
     * @param StorageService $storageService
     */
    public function __construct(
        private ExpenseRepository $expenseRepository,
        private StorageService $storageService,
    ) {}

    /**
     * Create an expense and count its receipt (if any) toward storage.
     *
     * @param GenericData $genericData
     * @return Expense
     */
    public function createExpense(GenericData $genericData): Expense
    {
        $accountId = (int) $genericData->userData->account_id;
        [$receiptUrl, $receiptSizeKb] = $this->extractReceiptMeta($genericData);

        $expense = $this->expenseRepository->createExpense($genericData);

        if ($receiptUrl) {
            $this->storageService->registerNewFile($accountId, $receiptUrl, $receiptSizeKb);
        }

        return $expense->load('category');
    }

    /**
     * Update an expense; when the receipt changed, replace/remove the old R2
     * object and adjust the storage counter accordingly.
     *
     * @param int $id
     * @param GenericData $genericData
     * @return Expense
     */
    public function updateExpense(int $id, GenericData $genericData): Expense
    {
        $accountId = (int) $genericData->userData->account_id;
        $oldReceipt = $this->expenseRepository->getExpenseById($id, $accountId)->receipt_url;

        $receiptProvided = array_key_exists('receiptUrl', $genericData->data);
        [$newReceipt, $newSizeKb] = $this->extractReceiptMeta($genericData);

        $expense = $this->expenseRepository->updateExpense($id, $genericData);

        if ($receiptProvided && $newReceipt !== $oldReceipt) {
            if ($newReceipt) {
                $this->storageService->registerReplacedFile($accountId, $oldReceipt, $newSizeKb, $newReceipt);
            } else {
                $this->storageService->removeFile($accountId, $oldReceipt);
            }
        }

        return $expense->load('category');
    }

    /**
     * Delete an expense and release its receipt's storage.
     *
     * @param int $id
     * @param int $accountId
     * @return void
     */
    public function deleteExpense(int $id, int $accountId): void
    {
        $receipt = $this->expenseRepository->getExpenseById($id, $accountId)->receipt_url;

        $this->expenseRepository->deleteExpense($id, $accountId);

        $this->storageService->removeFile($accountId, $receipt);
    }

    /**
     * Read receiptUrl + receiptSizeKb from the payload and strip the size (not a
     * DB column) so it isn't mass-assigned by the repository.
     *
     * @param GenericData $genericData
     * @return array{0: string|null, 1: float} [receiptUrl, receiptSizeKb]
     */
    private function extractReceiptMeta(GenericData $genericData): array
    {
        $url = $genericData->data['receiptUrl'] ?? null;
        $sizeKb = (float) ($genericData->data['receiptSizeKb'] ?? 0);

        unset($genericData->data['receiptSizeKb']);

        return [$url ?: null, $sizeKb];
    }
}
