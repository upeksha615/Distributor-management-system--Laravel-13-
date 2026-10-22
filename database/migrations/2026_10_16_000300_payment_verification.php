<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dealer payment verification: lifecycle statuses, cheque status, receiving bank account, attachments,
 * status history, duplicate flags and the payment slip inbox (submissions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // draft, submitted, pending_verification, under_review, verified, rejected, reversed
            $table->string('status', 25)->default('pending_verification')->change();
            $table->string('channel', 20)->default('office')->after('method'); // office, rep, inbox, email, whatsapp, portal
            $table->foreignId('company_bank_account_id')->nullable()->after('reference')->constrained()->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->after('cheque_number')->constrained()->nullOnDelete(); // sending / cheque bank
            $table->foreignId('bank_branch_id')->nullable()->after('bank_id')->constrained()->nullOnDelete();
            $table->string('cheque_status', 20)->nullable()->after('cheque_date'); // received, deposited, cleared, returned, cancelled
            $table->string('deposit_reference', 100)->nullable()->after('deposit_bank');
            $table->decimal('bank_charges', 14, 2)->default(0)->after('returned_date');
            $table->string('return_reason')->nullable()->after('bank_charges');
            $table->foreignId('verified_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->string('rejection_reason')->nullable()->after('verified_at');
            $table->foreignId('reversed_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversed_by');
            $table->string('reversal_reason')->nullable()->after('reversed_at');
            $table->foreignId('duplicate_of_id')->nullable()->after('reversal_reason')->constrained('payments')->nullOnDelete();
            $table->string('duplicate_check', 20)->nullable()->after('duplicate_of_id'); // null, flagged, cleared
            $table->index(['status', 'method']);
            $table->index('reference');
        });

        Schema::create('payment_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('field', 20)->default('status'); // status | cheque_status
            $table->string('from', 25)->nullable();
            $table->string('to', 25);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Slips / emails / WhatsApp messages / portal uploads waiting to be turned into payments.
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('channel', 20); // manual, email, whatsapp, portal
            $table->string('status', 20)->default('new'); // new, matched, info_requested, converted, rejected, duplicate
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender', 191)->nullable(); // email address / phone number / portal user
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('external_id', 191)->nullable()->unique(); // email Message-ID / WhatsApp message id
            $table->decimal('claimed_amount', 14, 2)->nullable();
            $table->date('claimed_date')->nullable();
            $table->string('claimed_reference', 100)->nullable();
            $table->string('claimed_method', 20)->nullable();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_refs')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->index(['status', 'received_at']);
        });

        // Private files (payment slips, cheque images, deposit slips). Never in public/.
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('kind', 30)->default('slip'); // slip, cheque_image, deposit_slip, supporting
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('sha256', 64)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Existing payments were accepted under the old process: treat them as verified, and derive cheque status.
        DB::table('payments')->where('status', 'received')->update(['status' => 'verified', 'verified_at' => DB::raw('created_at')]);
        DB::table('payments')->where('status', 'returned')->update(['status' => 'reversed', 'reversal_reason' => 'Cheque returned', 'reversed_at' => DB::raw('COALESCE(returned_date, updated_at)')]);
        DB::table('payments')->where('method', 'cheque')->update(['cheque_status' => DB::raw(
            "CASE WHEN returned_date IS NOT NULL THEN 'returned' WHEN cleared_date IS NOT NULL THEN 'cleared' WHEN deposited_date IS NOT NULL THEN 'deposited' ELSE 'received' END"
        )]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
        Schema::dropIfExists('payment_submissions');
        Schema::dropIfExists('payment_status_history');
        DB::table('payments')->where('status', 'verified')->update(['status' => 'received']);
        DB::table('payments')->where('status', 'reversed')->update(['status' => 'returned']);
        Schema::table('payments', function (Blueprint $table) {
            foreach (['company_bank_account_id', 'bank_id', 'bank_branch_id', 'verified_by', 'reversed_by', 'duplicate_of_id'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropIndex(['status', 'method']);
            $table->dropIndex(['reference']);
            $table->dropColumn(['channel', 'cheque_status', 'deposit_reference', 'bank_charges', 'return_reason', 'verified_at',
                'rejection_reason', 'reversed_at', 'reversal_reason', 'duplicate_check']);
        });
    }
};
