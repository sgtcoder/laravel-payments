<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('model');
            $table->string('email', 100)->nullable();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('payment_profile_id')->nullable()->constrained('payment_profiles')->nullOnDelete();
            $table->string('payment_type')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('transaction_tag')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('merchant_ref')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('error_message')->nullable();
            $table->string('payment_batch_id', 36)->nullable();
            $table->string('last_4', 4)->nullable();
            $table->string('exp_date', 10)->nullable();
            $table->string('card_type', 100)->nullable();
            $table->timestamps();
        });
    }
};
