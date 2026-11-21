<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The person's Microsoft Entra object ID, linked the first time they use "Sign in with Microsoft". */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'microsoft_oid')) {
            Schema::table('users', function (Blueprint $t) {
                $t->string('microsoft_oid', 64)->nullable()->unique()->after('password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'microsoft_oid')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropUnique(['microsoft_oid']);
                $t->dropColumn('microsoft_oid');
            });
        }
    }
};
