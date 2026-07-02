<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('name');
        });

        // System-Rollen (Mindestrollen) sicherstellen und als unlöschbar markieren.
        $systemRoles = [
            'admin' => 'Administrator',
            'user'  => 'Benutzer',
        ];
        foreach ($systemRoles as $id => $name) {
            if (DB::table('roles')->where('role_id', $id)->exists()) {
                DB::table('roles')->where('role_id', $id)->update([
                    'is_system'  => true,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('roles')->insert([
                    'role_id'    => $id,
                    'name'       => $name,
                    'is_system'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Grundregel: jeder bestehende Benutzer erhält die Rolle "user".
        $already = DB::table('user_roles')->where('role_id', 'user')->pluck('user_id')->all();
        $rows = DB::table('users')
            ->when($already, fn ($q) => $q->whereNotIn('id', $already))
            ->pluck('id')
            ->map(fn ($id) => ['user_id' => $id, 'role_id' => 'user'])
            ->all();
        if ($rows) {
            DB::table('user_roles')->insert($rows);
        }
    }

    public function down(): void
    {
        // Die in dieser Migration angelegte "user"-Rolle entfernen
        // (cascade löscht die zugehörigen user_roles-Einträge), dann die Spalte.
        DB::table('roles')->where('role_id', 'user')->delete();

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
