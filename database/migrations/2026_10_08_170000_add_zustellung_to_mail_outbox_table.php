<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zustellung je Ausgangskorb-Zeile – was nach „versendet" geschah.
 *
 * „Versendet" heißt nur: unser Mailserver hat die Mail angenommen. Meldet er
 * später zurück, wie die Zustellung beim Empfänger ausging (zugestellt,
 * verzögert, abgewiesen), landet das hier – siehe App\Support\Zustellmeldungen.
 * Je Empfänger in `zustellung_empfaenger`, zusammengefasst in `zustellung`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_outbox', function (Blueprint $table) {
            if (! Schema::hasColumn('mail_outbox', 'zustellung')) {
                $table->string('zustellung', 20)->nullable()->after('message_id');
                $table->text('zustellung_grund')->nullable()->after('zustellung');
                $table->dateTime('zustellung_am')->nullable()->after('zustellung_grund');
                $table->json('zustellung_empfaenger')->nullable()->after('zustellung_am');
            }
        });

        // Rückmeldungen werden über die Message-ID bzw. Warteschlangen-Kennung zugeordnet.
        if (! collect(Schema::getIndexes('mail_outbox'))->contains(fn ($i) => $i['columns'] === ['message_id'])) {
            Schema::table('mail_outbox', function (Blueprint $table) {
                $table->index('message_id', 'mail_outbox_message_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('mail_outbox', function (Blueprint $table) {
            $table->dropIndex('mail_outbox_message_id_index');
            $table->dropColumn(['zustellung', 'zustellung_grund', 'zustellung_am', 'zustellung_empfaenger']);
        });
    }
};
