<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Signing in with a second factor (docs/architecture/01, section 1): an
 * authenticator app and its recovery codes, required of the owner, the
 * admins and the accountants, open to anyone else. Both are encrypted with
 * the app key; a secret is saved only once its first code was right.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable();          // encrypted
            $table->text('app_authentication_recovery_codes')->nullable();  // encrypted, hashed codes
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']));
    }
};
