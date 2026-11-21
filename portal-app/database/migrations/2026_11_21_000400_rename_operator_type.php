<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Earlier installs used a company-specific name for the operator's organisation type and account; make them generic. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('organisations')->whereNotIn('type', ['operator', 'client', 'partner', 'prospect', 'supplier', 'other'])->update(['type' => 'operator']);
        if (! DB::table('accounts')->where('slug', 'main')->exists() && ($first = DB::table('accounts')->orderBy('id')->first())) {
            DB::table('accounts')->where('id', $first->id)->update(['slug' => 'main']);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the old names aren't kept.
    }
};
