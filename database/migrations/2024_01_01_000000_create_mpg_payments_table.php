<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpg_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->index(); // The payment reference ID

            $table->string('status')->default('pending')->index(); // pending, paid, failed, refunded
            $table->string('gateway')->nullable(); // chip, toyyibpay, etc
            $table->string('transaction_id')->nullable(); // Gateway's ID

            $table->bigInteger('amount')->unsigned(); // In cents
            $table->string('currency', 3)->default('MYR');
            $table->string('description');

            // Customer Info
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_phone')->nullable();

            // Meta (Items, URLs, Custom Settings)
            $table->json('items')->nullable();
            $table->json('metadata')->nullable(); // For implementation flexible data

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpg_payments');
    }
};
