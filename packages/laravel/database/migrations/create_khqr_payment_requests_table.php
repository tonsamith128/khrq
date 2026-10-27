<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('khqr_payment_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('order_reference', 128)->unique();
            $table->char('lookup_md5', 32)->unique();
            $table->string('recipient_account_id', 95);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 16)->default('pending')->index();
            $table->char('transaction_hash', 64)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Destructive reversal; normal production policy should archive paid
        // records before rolling back this migration.
        Schema::dropIfExists('khqr_payment_requests');
    }
};

