<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds 'voided' status to the bill_status enum in tb_customer_bills table.
     */
    public function up(): void
    {
        try {
            // For MySQL, modify the enum
            DB::statement("ALTER TABLE tb_customer_bills MODIFY COLUMN bill_status ENUM('paid', 'partial', 'active', 'voided') DEFAULT 'active'");
        } catch (\Exception $e) {
            // For SQLite (tests), we need to recreate the column since it doesn't support MODIFY COLUMN
            // SQLite stores enum as string with CHECK constraint
            if (DB::getDriverName() === 'sqlite') {
                // Drop the CHECK constraint and recreate column with new constraint
                DB::statement("PRAGMA foreign_keys=off");
                DB::statement("BEGIN TRANSACTION");

                // Create new table with updated enum
                DB::statement("CREATE TABLE tb_customer_bills_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_id INTEGER NOT NULL,
                    customer_id INTEGER NOT NULL,
                    gross_amount DECIMAL(10,2) NOT NULL,
                    discount_percentage DECIMAL(10,2) NOT NULL,
                    net_amount DECIMAL(10,2) NOT NULL,
                    paid_amount DECIMAL(10,2) NOT NULL,
                    bill_date DATE NOT NULL,
                    bill_status TEXT DEFAULT 'active' CHECK(bill_status IN ('paid', 'partial', 'active', 'voided')),
                    bill_type VARCHAR(255) NOT NULL,
                    membership_plan_id INTEGER,
                    custom_service VARCHAR(255),
                    created_by INTEGER,
                    updated_by INTEGER,
                    deleted_at DATETIME,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (customer_id) REFERENCES tb_customers(id),
                    FOREIGN KEY (membership_plan_id) REFERENCES tb_membership_plan(id) ON DELETE SET NULL
                )");

                // Copy data
                DB::statement("INSERT INTO tb_customer_bills_new SELECT * FROM tb_customer_bills");

                // Drop old table and rename
                DB::statement("DROP TABLE tb_customer_bills");
                DB::statement("ALTER TABLE tb_customer_bills_new RENAME TO tb_customer_bills");

                DB::statement("COMMIT");
                DB::statement("PRAGMA foreign_keys=on");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE tb_customer_bills MODIFY COLUMN bill_status ENUM('paid', 'partial', 'active') DEFAULT 'active'");
        } catch (\Exception $e) {
            // For SQLite, revert the column
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("PRAGMA foreign_keys=off");
                DB::statement("BEGIN TRANSACTION");

                DB::statement("CREATE TABLE tb_customer_bills_old (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_id INTEGER NOT NULL,
                    customer_id INTEGER NOT NULL,
                    gross_amount DECIMAL(10,2) NOT NULL,
                    discount_percentage DECIMAL(10,2) NOT NULL,
                    net_amount DECIMAL(10,2) NOT NULL,
                    paid_amount DECIMAL(10,2) NOT NULL,
                    bill_date DATE NOT NULL,
                    bill_status TEXT DEFAULT 'active' CHECK(bill_status IN ('paid', 'partial', 'active')),
                    bill_type VARCHAR(255) NOT NULL,
                    membership_plan_id INTEGER,
                    custom_service VARCHAR(255),
                    created_by INTEGER,
                    updated_by INTEGER,
                    deleted_at DATETIME,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (customer_id) REFERENCES tb_customers(id),
                    FOREIGN KEY (membership_plan_id) REFERENCES tb_membership_plan(id) ON DELETE SET NULL
                )");

                DB::statement("INSERT INTO tb_customer_bills_old SELECT * FROM tb_customer_bills WHERE bill_status != 'voided'");
                DB::statement("DROP TABLE tb_customer_bills");
                DB::statement("ALTER TABLE tb_customer_bills_old RENAME TO tb_customer_bills");

                DB::statement("COMMIT");
                DB::statement("PRAGMA foreign_keys=on");
            }
        }
    }
};
