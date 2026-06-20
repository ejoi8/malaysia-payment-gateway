<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index the gateway transaction id.
 *
 * Refund persistence resolves the payable via findByTransactionId(); without an
 * index that's a full table scan on every refund.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mpg_payments', function (Blueprint $table) {
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('mpg_payments', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
        });
    }
};
