<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resource planner (replaces the "Resources Tracker" spreadsheet): one list of projects, one list of
 * people, and how many days each person spends on each project each week.
 * Plus Power BI reports embedded for management, and API keys for Power BI / Power Automate / agents.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $t) {
                $t->id();
                $t->foreignId('account_id')->constrained()->cascadeOnDelete();
                $t->string('name', 120);
                $t->string('position', 120)->nullable();          // e.g. MEP Manager, Senior QS
                $t->string('team', 60)->nullable();               // Project Management | Commercial | Design
                $t->decimal('days_per_week', 3, 1)->default(5);   // part-timers less
                $t->date('starts_on')->nullable();                // new starters
                $t->date('leaves_on')->nullable();
                $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $t->boolean('active')->default(true);
                $t->unsignedSmallInteger('sort')->default(0);
                $t->timestamps();
                $t->unique(['account_id', 'name']);
            });
        }

        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $t) {
                $t->id();
                $t->foreignId('account_id')->constrained()->cascadeOnDelete();
                $t->string('number', 20)->nullable();             // P0223, or blank while TBC
                $t->string('name', 190);
                $t->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete(); // client
                $t->string('client_name', 160)->nullable();       // when the client isn't in the CRM yet
                $t->foreignId('lead_id')->nullable()->constrained('staff')->nullOnDelete();
                $t->string('type', 40)->nullable();               // TSM | Validations | QS | Design | ...
                $t->decimal('days_allowed', 7, 1)->nullable();
                $t->date('completion_on')->nullable();
                $t->string('completion_note', 60)->nullable();    // "April/August", "-"
                $t->string('status', 12)->default('active');      // active | complete
                $t->boolean('internal')->default(false);          // Holiday, Training, Overhead
                $t->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['account_id', 'status']);
            });
        }

        if (! Schema::hasTable('allocations')) {
            Schema::create('allocations', function (Blueprint $t) {
                $t->id();
                $t->foreignId('account_id')->constrained()->cascadeOnDelete();
                $t->foreignId('project_id')->constrained()->cascadeOnDelete();
                $t->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
                $t->date('week');                                  // the Friday the week ends on
                $t->decimal('days', 3, 1);
                $t->timestamps();
                $t->unique(['project_id', 'staff_id', 'week']);
                $t->index(['account_id', 'week']);
                $t->index(['staff_id', 'week']);
            });
        }

        if (! Schema::hasTable('dashboards')) {
            Schema::create('dashboards', function (Blueprint $t) {
                $t->id();
                $t->foreignId('account_id')->constrained()->cascadeOnDelete();
                $t->string('title', 120);
                $t->string('description', 255)->nullable();
                $t->string('embed_url', 1000);
                $t->json('roles');                                 // staff roles that can see it
                $t->unsignedSmallInteger('sort')->default(0);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('api_keys')) {
            Schema::create('api_keys', function (Blueprint $t) {
                $t->id();
                $t->foreignId('account_id')->constrained()->cascadeOnDelete();
                $t->string('name', 120);                           // "Power BI", "Power Automate", "Agent"
                $t->string('key_hash', 64)->unique();
                $t->string('prefix', 12);                          // first characters, to recognise it
                $t->timestamp('last_used_at')->nullable();
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('revoked_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['api_keys', 'dashboards', 'allocations', 'projects', 'staff'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
