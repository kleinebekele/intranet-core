<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'totp_app')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            // Gedankenstütze: in welcher App der Schlüssel liegt (z. B. "Vaultwarden").
            $table->string('totp_app', 100)->nullable()->after('totp_confirmed_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'totp_app')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('totp_app');
            });
        }
    }
};
