<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The revenue screen and its spreadsheet read an academy's money by day
 * (Queries\RevenueBoard): payments by when they arrived, invoices by when
 * they went out. Without these, every look at a period reads all of the
 * academy's payments and invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->index(['academy_id', 'received_at']));
        Schema::table('invoices', fn (Blueprint $table) => $table->index(['academy_id', 'issued_at']));
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropIndex(['academy_id', 'issued_at']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex(['academy_id', 'received_at']));
    }
};
