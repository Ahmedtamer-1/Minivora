<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Finance (Documentation §5.11 "Finance" group): chart of accounts, journal entries and lines,
// accounting periods (month close), expenses and budgets, plus per-store finance settings.
// Money columns hold integer piasters. FR-LED-01..08, FR-FPA-01..05
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10)->default('');
            $table->string('name_en', 120)->default('');
            $table->string('name_ar', 120)->default('');
            $table->string('type', 10)->default('expense');          // asset | liability | equity | revenue | expense
            $table->string('subtype', 20)->default('other');         // cash | bank | clearing | receivable | inventory | tax | payable | cogs | variable | fixed | contra | ...
            $table->boolean('is_system')->default(false);            // used by posting rules: cannot be removed
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number')->default(0);           // per store, from 1
            $table->date('entry_date')->nullable();
            $table->string('memo', 255)->default('');
            $table->string('source_type', 20)->default('manual');    // order | expense | manual | opening | adjustment | reversal
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_key', 80)->nullable();            // idempotency: "order:12:sale"
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->unsignedBigInteger('total_debit')->default(0);
            $table->unsignedBigInteger('total_credit')->default(0);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('posted_by_type', 10)->default('system'); // user | agent | system
            $table->timestamps();

            $table->unique(['store_id', 'number']);
            $table->unique(['store_id', 'source_key']);
            $table->index(['store_id', 'entry_date']);
            $table->index(['store_id', 'source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->string('memo', 255)->nullable();
            $table->date('entry_date')->nullable();                  // copied from the entry for fast period sums
            $table->timestamps();

            $table->index(['store_id', 'account_id', 'entry_date']);
        });

        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('month')->nullable();                       // first day of the month
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('checks')->nullable();                      // the checklist as it stood at close
            $table->timestamps();

            $table->unique(['store_id', 'month']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('expense_date')->nullable();
            $table->string('vendor', 120)->nullable();
            $table->string('description', 255)->default('');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();      // expense account
            $table->string('paid_from', 12)->default('cash');        // cash | bank | card | payable
            $table->unsignedBigInteger('amount')->default(0);        // gross, as on the receipt
            $table->unsignedBigInteger('vat')->default(0);           // input VAT inside the amount
            $table->date('due_date')->nullable();                    // for bills paid later
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_with', 12)->nullable();             // cash | bank | card, once a bill is paid
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('payment_entry_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'expense_date']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('month')->nullable();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'month', 'account_id']);
        });

        Schema::create('finance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('vat_registered')->default(false);
            $table->unsignedSmallInteger('vat_rate_bps')->default(1400);        // 14% standard rate
            $table->unsignedSmallInteger('gateway_fee_bps')->default(275);      // card / wallet fee, percent part
            $table->unsignedInteger('gateway_fee_fixed')->default(300);         // piasters per payment
            $table->unsignedBigInteger('cash_safety_level')->default(1000000);  // warn below this
            $table->timestamps();
        });

        // FR-LED-03 in the database itself: an entry whose debits and credits differ is refused.
        // SQLite cannot add a constraint to an existing table; the service and model guard it there.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_balanced CHECK (total_debit = total_credit)');
            DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_side CHECK ((debit = 0 AND credit > 0) OR (credit = 0 AND debit > 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_settings');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('periods');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
