<?php

namespace App\Console\Commands;

use App\Repositories\Account\AccountReferralRepository;
use App\Services\Account\ReferralService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EvaluatePendingReferralsCommand extends Command
{
    protected $signature = 'referrals:evaluate-pending
                            {--account= : Only evaluate pending referrals for this referrer account id}';

    protected $description = 'Safety net: qualify pending referrals whose invited account has since started a paid subscription.';

    public function handle(
        AccountReferralRepository $referralRepository,
        ReferralService $referralService
    ): int {
        $accountId = $this->option('account') ? (int) $this->option('account') : null;
        $pending = $referralRepository->getPending($accountId);

        $qualified = 0;
        foreach ($pending as $referral) {
            $before = $referral->status;
            $referralService->evaluateInvitedAccount((int) $referral->invited_account_id);
            if ($referral->fresh()->status !== $before) {
                $qualified++;
            }
        }

        $this->info("Evaluated {$pending->count()} pending referral(s); {$qualified} newly qualified.");
        Log::info('Referrals: evaluated pending', [
            'evaluated' => $pending->count(),
            'newly_qualified' => $qualified,
        ]);

        return Command::SUCCESS;
    }
}
