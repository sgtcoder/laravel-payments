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
        Schema::create('payment_profiles', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->string('payment_gateway')->nullable();
            $table->string('payment_method_id');
            $table->string('last_4', 4)->nullable();
            $table->string('exp_date', 10)->nullable();
            $table->string('card_type', 100)->nullable();
            $table->string('cardholder_name')->nullable();
            $table->timestamps();
        });
    }
};
