<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('model');
            $table->string('order_type')->nullable();
            $table->decimal('order_total', 10, 2)->default(0);
            $table->string('order_status')->nullable();
            $table->string('transaction_id')->nullable();
            $table->uuid('order_uuid')->nullable()->unique()->index();
            $table->json('cart_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
