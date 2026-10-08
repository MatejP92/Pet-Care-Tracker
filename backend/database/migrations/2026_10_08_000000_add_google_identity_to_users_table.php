<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep Google-only accounts without assigning a known sign-in password.
        DB::table('users')
            ->whereNull('password')
            ->select('id')
            ->eachById(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->whereNull('password')
                    ->update([
                        'password' => Hash::make(Str::random(64)),
                    ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });
    }
};
