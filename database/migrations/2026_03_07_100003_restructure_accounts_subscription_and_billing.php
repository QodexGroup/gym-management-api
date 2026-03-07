<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add billing columns to accounts (no business_name, tax_id, vat_number)
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('owner_email');
            $table->string('billing_email')->nullable()->after('legal_name');
            $table->string('address_line_1')->nullable()->after('billing_email');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city')->nullable()->after('address_line_2');
            $table->string('state_province')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('state_province');
            $table->string('country', 2)->nullable()->after('postal_code');
        });

        // 2. Copy billing data from account_billing_information to accounts
        $rows = DB::table('account_billing_information')->get();
        foreach ($rows as $row) {
            DB::table('accounts')->where('id', $row->account_id)->update([
                'legal_name' => $row->legal_name,
                'billing_email' => $row->billing_email,
                'address_line_1' => $row->address_line_1,
                'address_line_2' => $row->address_line_2,
                'city' => $row->city,
                'state_province' => $row->state_province,
                'postal_code' => $row->postal_code,
                'country' => $row->country,
            ]);
        }

        // 3. Add account_invoice_id to account_subscription_requests (nullable for backfill)
        Schema::table('account_subscription_requests', function (Blueprint $table) {
            $table->foreignId('account_invoice_id')->nullable()->after('account_id')
                ->constrained('account_invoices')->nullOnDelete();
        });

        // 4. Backfill: create account_subscription_plan + invoice per existing request, then link
        $requests = DB::table('account_subscription_requests')->whereNotNull('subscription_plan_id')->get();
        foreach ($requests as $req) {
            $account = DB::table('accounts')->find($req->account_id);
            $planId = $req->subscription_plan_id ?? $account->subscription_plan_id ?? null;
            if (!$planId) {
                continue;
            }
            $plan = DB::table('platform_subscription_plans')->find($planId);
            $asp = DB::table('account_subscription_plans')->where('account_id', $req->account_id)->first();
            if (!$asp) {
                $aspId = DB::table('account_subscription_plans')->insertGetId([
                    'account_id' => $req->account_id,
                    'platform_subscription_plan_id' => $planId,
                    'trial_starts_at' => null,
                    'trial_ends_at' => null,
                    'subscription_starts_at' => now(),
                    'subscription_ends_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $aspId = $asp->id;
            }
            $invoiceNumber = '#'.str_pad((string) $req->id, 7, '0', STR_PAD_LEFT);
            $status = $req->status === 'approved' ? 'paid' : ($req->status === 'rejected' ? 'void' : 'issued');
            $invoiceId = DB::table('account_invoices')->insertGetId([
                'account_id' => $req->account_id,
                'account_subscription_plan_id' => $aspId,
                'invoice_number' => $invoiceNumber,
                'billing_period' => now()->format('mdY'),
                'plan_name' => $plan->name ?? null,
                'plan_interval' => $plan->interval ?? null,
                'plan_price' => $plan->price ?? 0,
                'billing_cycle_start_at' => now()->startOfMonth(),
                'status' => $status,
                'invoice_details' => json_encode(['migrated_from_request_id' => $req->id]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('account_subscription_requests')->where('id', $req->id)->update(['account_invoice_id' => $invoiceId]);
        }

        // 5. Drop subscription_plan_id from account_subscription_requests
        Schema::table('account_subscription_requests', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn('subscription_plan_id');
        });

        // 6. Drop subscription columns from accounts
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn(['subscription_plan_id', 'trial_ends_at', 'current_period_ends_at']);
        });

        // 7. Drop account_billing_information
        Schema::dropIfExists('account_billing_information');
    }

    public function down(): void
    {
        Schema::create('account_billing_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('legal_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->nullable();
            $table->timestamps();
            $table->unique('account_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->after('subscription_status')
                ->constrained('platform_subscription_plans')->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_plan_id');
            $table->timestamp('current_period_ends_at')->nullable()->after('trial_ends_at');
        });

        Schema::table('account_subscription_requests', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->after('account_id')
                ->constrained('platform_subscription_plans')->onDelete('cascade');
        });

        foreach (DB::table('accounts')->get() as $acc) {
            DB::table('account_billing_information')->insert([
                'account_id' => $acc->id,
                'legal_name' => $acc->legal_name,
                'billing_email' => $acc->billing_email,
                'address_line_1' => $acc->address_line_1,
                'address_line_2' => $acc->address_line_2,
                'city' => $acc->city,
                'state_province' => $acc->state_province,
                'postal_code' => $acc->postal_code,
                'country' => $acc->country,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('account_subscription_requests', function (Blueprint $table) {
            $table->dropForeign(['account_invoice_id']);
            $table->dropColumn('account_invoice_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'billing_email', 'address_line_1', 'address_line_2',
                'city', 'state_province', 'postal_code', 'country',
            ]);
        });
    }
};
