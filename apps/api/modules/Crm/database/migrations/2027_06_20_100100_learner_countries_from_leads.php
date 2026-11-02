<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * A family's prices are in the region of the payer's or the learner's country
 * (Billing's priceRegionFor, read from People), so the learners and parents
 * the CRM made before PrepareLearner gave them their lead's country take it
 * now, once, where they have none (crm:learner-countries: in each academy as
 * that academy, through People's contract; tenant tables are row-secured, so
 * plain SQL here would see none of their rows). Nothing to undo: a country
 * filled in is the family's own from then on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('crm:learner-countries');
    }

    public function down(): void {}
};
