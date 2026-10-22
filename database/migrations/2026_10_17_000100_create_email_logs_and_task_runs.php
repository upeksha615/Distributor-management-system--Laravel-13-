<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every email the system tries to send, with the real outcome (sent / logged only / failed).
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40); // password_reset, invoice, payment_verified, payment_rejected, cheque_returned, overdue_reminder, daily_summary, test
            $table->string('to', 191);
            $table->string('subject', 255);
            $table->nullableMorphs('related');
            $table->string('status', 20)->default('pending'); // pending, sent, logged, failed
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        // History of scheduled / background tasks (backups, summaries, reminders).
        Schema::create('task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('task', 60);
            $table->string('status', 20)->default('running'); // running, success, failed
            $table->text('output')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->index(['task', 'started_at']);
        });

        Permissions::sync();
        Permissions::grantDefaults(['invoices.email', 'notifications.daily_summary', 'system.manage']);
    }

    public function down(): void
    {
        Schema::dropIfExists('task_runs');
        Schema::dropIfExists('email_logs');
    }
};
