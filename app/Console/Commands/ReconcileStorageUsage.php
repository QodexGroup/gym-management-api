<?php

namespace App\Console\Commands;

use App\Repositories\Core\StorageRepository;
use App\Services\Core\StorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileStorageUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:reconcile-usage {--account_id= : Optional account ID to reconcile a single account; omit to run for all active accounts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute each account\'s storage usage from R2 (fallback: DB) and update the live counter';

    /**
     * @param StorageService $storageService
     * @param StorageRepository $storageRepository
     */
    public function __construct(
        private StorageService $storageService,
        private StorageRepository $storageRepository,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command: recalculate storage usage for the target
     * account(s) and update the meter.
     *
     * @return int Command exit code.
     */
    public function handle(): int
    {
        $accountId = $this->option('account_id');

        $accountIds = ($accountId !== null && $accountId !== '')
            ? [(int) $accountId]
            : $this->storageRepository->activeAccountIds();

        if ($this->output) {
            $this->info('Reconciling storage usage for ' . count($accountIds) . ' account(s)...');
        }

        $reconciled = 0;

        foreach ($accountIds as $id) {
            try {
                $account = $this->storageRepository->findAccountById($id);
                if (!$account) {
                    continue;
                }

                $usage = $this->storageService->recalculateForAccount($account);
                $reconciled++;

                if ($this->output) {
                    $this->line("✓ Account {$id}: {$usage->usedLabel} / {$usage->limitLabel} ({$usage->usedPercent}%)");
                }
            } catch (\Throwable $th) {
                if ($this->output) {
                    $this->error("✗ Account {$id}: {$th->getMessage()}");
                }
                Log::error('Storage reconcile failed', [
                    'account_id' => $id,
                    'error' => $th->getMessage(),
                ]);
            }
        }

        if ($this->output) {
            $this->info("Storage reconcile completed. {$reconciled} account(s) updated.");
        }
        Log::info('Storage reconcile completed', ['accounts_reconciled' => $reconciled]);

        return Command::SUCCESS;
    }
}
