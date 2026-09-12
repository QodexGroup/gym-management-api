<?php

use App\Constant\PlatformCommandConstant as Cmd;

return [

    /*
    |--------------------------------------------------------------------------
    | Console-runnable commands
    |--------------------------------------------------------------------------
    |
    | An ALLOWLIST, not a listing. A command is runnable from the operations
    | console if and only if it appears here — `Artisan::all()` is never
    | enumerated and the console can never name a command this file omits.
    |
    | That is the whole security posture of this surface. The service token
    | bypasses every account boundary already; if the console could run any
    | artisan command, a leaked token would be remote code execution on this
    | product — `platform:token` would mint an attacker a fresh token, and
    | `db:wipe` needs no explanation. Both are absent below and must stay absent.
    |
    | Adding a command here is the ONLY step needed to expose it: the console
    | discovers this list at runtime per project, so a command that exists in
    | GymHub and not in another product simply does not appear for that product.
    |
    | Each entry:
    |   key          Stable console-facing identifier. NEVER reuse or rename —
    |                it is what the audit log records.
    |   command      The artisan signature name actually run.
    |   label        What the operator sees.
    |   description  What it does, in the operator's terms, not the developer's.
    |   scope        GLOBAL runs across every account; ACCOUNT runs for one.
    |   accountParam How the account id is passed, for ACCOUNT scope only:
    |                ['type' => 'option'|'argument', 'name' => '...'].
    |   destructive  true makes the console demand a typed confirmation.
    |   timeout      Seconds before the run is abandoned. Keep under the
    |                console's own HTTP timeout or the operator sees a
    |                connection error instead of the output.
    |   params       Extra inputs the console renders as form fields.
    |
    */

    'commands' => [

        [
            'key' => 'billing.generate-invoices',
            'command' => 'account-billing:generate-invoices',
            'label' => 'Generate invoices',
            'description' => 'Issue invoices for every account whose subscription interval is due. Normally runs on the 5th; run it here when the schedule was missed.',
            'scope' => Cmd::SCOPE_GLOBAL,
            'destructive' => true,
            'timeout' => 300,
            'params' => [
                [
                    'name' => 'force',
                    'label' => 'Run even though today is not the 5th',
                    'input' => Cmd::INPUT_BOOLEAN,
                    'required' => false,
                    'hint' => 'The command refuses on any other day and exits cleanly without issuing anything. Tick this to override the date guard.',
                ],
            ],
        ],

        /*
        | The same command scoped to one account. Carries the SAME force flag:
        | the date guard applies to a single-account run exactly as it does to
        | the whole cycle, so an operator invoicing one account off-cycle has to
        | make the same decision.
        */
        [
            'key' => 'billing.generate-account-invoice',
            'command' => 'account-billing:generate-invoices',
            'label' => 'Generate invoice for this account',
            'description' => "Issue this account's invoice for the current cycle, if one is due. Skipped when it is still prepaid, already invoiced for the cycle, or has an unpaid invoice outstanding.",
            'scope' => Cmd::SCOPE_ACCOUNT,
            'accountParam' => ['type' => 'option', 'name' => 'account_id'],
            'destructive' => true,
            'timeout' => 180,
            'params' => [
                [
                    'name' => 'force',
                    'label' => 'Run even though today is not the 5th',
                    'input' => Cmd::INPUT_BOOLEAN,
                    'required' => false,
                    'hint' => 'Without this the command exits cleanly without issuing anything on any day but the 5th.',
                ],
            ],
        ],

        [
            'key' => 'billing.lock-accounts',
            'command' => 'account-billing:lock-accounts',
            'label' => 'Lock unpaid accounts',
            'description' => 'Lock every account with an unpaid invoice for the current period. Normally runs on the 10th. Locked owners can still sign in to pay.',
            'scope' => Cmd::SCOPE_GLOBAL,
            'destructive' => true,
            'timeout' => 300,
            'params' => [
                [
                    'name' => 'force',
                    'label' => 'Run even though today is not the 10th',
                    'input' => Cmd::INPUT_BOOLEAN,
                    'required' => false,
                    'hint' => 'The command refuses on any other day and exits cleanly without locking anything. Tick this to override the date guard.',
                ],
            ],
        ],

        [
            'key' => 'billing.process-reactivations',
            'command' => 'account-billing:process-reactivations',
            'label' => 'Process reactivation payments',
            'description' => 'Reactivate accounts whose reactivation fee was approved, void their old unpaid invoices and apply the free month.',
            'scope' => Cmd::SCOPE_GLOBAL,
            'destructive' => true,
            'timeout' => 300,
            'params' => [
                [
                    'name' => 'limit',
                    'label' => 'Maximum requests to scan',
                    'input' => Cmd::INPUT_NUMBER,
                    'required' => false,
                    'hint' => 'Leave blank for the command default of 200.',
                ],
            ],
        ],

        /*
        | The same command, scoped to one account. Two keys onto one artisan
        | command is fine and deliberate: the scope decides which list the
        | console shows it in, and the audit row records which one was run.
        */
        [
            'key' => 'billing.process-account-reactivation',
            'command' => 'account-billing:process-reactivations',
            'label' => 'Process reactivation payment',
            'description' => "Reactivate this account if its reactivation fee was approved, void its old unpaid invoices and apply the free month.",
            'scope' => Cmd::SCOPE_ACCOUNT,
            'accountParam' => ['type' => 'option', 'name' => 'account_id'],
            'destructive' => true,
            'timeout' => 180,
            'params' => [],
        ],

        [
            'key' => 'referrals.evaluate-pending',
            'command' => 'referrals:evaluate-pending',
            'label' => 'Evaluate pending referrals',
            'description' => 'Safety net: qualify pending referrals whose invited account has since started a paid subscription.',
            'scope' => Cmd::SCOPE_GLOBAL,
            'destructive' => false,
            'timeout' => 180,
            'params' => [],
        ],

        [
            'key' => 'referrals.evaluate-for-account',
            'command' => 'referrals:evaluate-pending',
            'label' => 'Evaluate referrals made by this account',
            'description' => 'Qualify pending referrals where this account is the REFERRER — not where it was the one invited.',
            'scope' => Cmd::SCOPE_ACCOUNT,
            // Note the option is `account`, not `account_id`. The names genuinely
            // differ between commands; each entry matches its own signature.
            'accountParam' => ['type' => 'option', 'name' => 'account'],
            'destructive' => false,
            'timeout' => 180,
            'params' => [],
        ],

        [
            'key' => 'membership.check-expiration',
            'command' => 'membership:check-expiration',
            'label' => 'Send expiring-membership notices',
            'description' => 'Find memberships expiring within the notice threshold and send their notifications. Sends real messages to members.',
            'scope' => Cmd::SCOPE_ACCOUNT,
            'accountParam' => ['type' => 'option', 'name' => 'account_id'],
            'destructive' => false,
            'timeout' => 180,
            'params' => [],
        ],

        [
            'key' => 'membership.update-expired-status',
            'command' => 'membership:update-expired-status',
            'label' => 'Refresh expired memberships',
            'description' => 'Mark memberships past their end date as expired. Safe to re-run; use it when member statuses look stale.',
            'scope' => Cmd::SCOPE_ACCOUNT,
            'accountParam' => ['type' => 'option', 'name' => 'account_id'],
            'destructive' => false,
            'timeout' => 180,
            'params' => [],
        ],

        [
            'key' => 'storage.reconcile-usage',
            'command' => 'storage:reconcile-usage',
            'label' => 'Recalculate storage usage',
            'description' => "Recompute this account's storage counter from R2 (falling back to the database). Read-only apart from the counter itself.",
            'scope' => Cmd::SCOPE_ACCOUNT,
            'accountParam' => ['type' => 'option', 'name' => 'account_id'],
            'destructive' => false,
            'timeout' => 300,
            'params' => [],
        ],

        [
            'key' => 'billing.deactivate-account',
            'command' => 'account-billing:deactivate-accounts',
            'label' => 'Deactivate if long unpaid',
            'description' => 'Deactivate this account if it has been locked and unpaid long enough to qualify. Its owner loses access until reactivation.',
            'scope' => Cmd::SCOPE_ACCOUNT,
            'accountParam' => ['type' => 'option', 'name' => 'accountId'],
            'destructive' => true,
            'timeout' => 180,
            'params' => [],
        ],

        /*
        | payment-request:approve / :reject are deliberately NOT here. The
        | console decides payments through /platform/payment-requests/{id}/
        | approve|reject, which targets ONE request by id, stamps the operator
        | into platform_actor and is idempotency-guarded. The artisan commands
        | take an ACCOUNT id and act on whatever request they find, so exposing
        | them would give the console a second, weaker path to the same money.
        |
        | platform:token is NOT here, and must never be. See the header.
        */
    ],
];
