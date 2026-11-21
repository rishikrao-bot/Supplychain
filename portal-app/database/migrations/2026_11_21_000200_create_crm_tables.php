<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM: companies are the existing organisations (clients, partners, plus prospects, suppliers and
 * others), with their contacts, activity history, pipeline (enquiries and tenders) and tasks.
 * Tasks sync with each person's Microsoft To Do. dataverse_id columns record what was imported from
 * the old Dataverse CRM, so the import can be re-run without duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orgCols = Schema::getColumnListing('organisations');
        Schema::table('organisations', function (Blueprint $t) use ($orgCols) {
            foreach ([
                'phone' => fn () => $t->string('phone', 40)->nullable(),
                'email' => fn () => $t->string('email', 190)->nullable(),
                'website' => fn () => $t->string('website', 190)->nullable(),
                'postcode' => fn () => $t->string('postcode', 12)->nullable(),
                'sector' => fn () => $t->string('sector', 80)->nullable(),
                'owner_id' => fn () => $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(),
                'dataverse_id' => fn () => $t->string('dataverse_id', 36)->nullable()->index(),
            ] as $col => $add) {
                if (! in_array($col, $orgCols, true)) {
                    $add();
                }
            }
        });

        $userCols = Schema::getColumnListing('users');
        Schema::table('users', function (Blueprint $t) use ($userCols) {
            // Delegated Microsoft 365 access (Outlook mail, To Do) for this person. Stored encrypted.
            if (! in_array('microsoft_refresh_token', $userCols, true)) {
                $t->text('microsoft_refresh_token')->nullable();
                $t->timestamp('microsoft_connected_at')->nullable();
                $t->string('todo_list_id')->nullable();
                $t->timestamp('todo_synced_at')->nullable();
            }
        });

        if (! Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('account_id')->constrained()->cascadeOnDelete();
                $t->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
                $t->string('first_name', 80);
                $t->string('last_name', 80)->nullable();
                $t->string('job_title', 120)->nullable();
                $t->string('email', 190)->nullable();
                $t->string('phone', 40)->nullable();
                $t->string('mobile', 40)->nullable();
                $t->text('notes')->nullable();
                $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('dataverse_id', 36)->nullable()->index();
                $t->timestamps();
                $t->index(['account_id', 'last_name']);
                $t->index(['account_id', 'email']);
            });
        }

        if (! Schema::hasTable('opportunities')) {
            Schema::create('opportunities', function (Blueprint $t) {
                $t->id();
                $t->foreignId('account_id')->constrained()->cascadeOnDelete();
                $t->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
                $t->string('title', 190);
                $t->string('kind', 20)->default('enquiry');  // enquiry | tender | framework
                $t->string('stage', 20)->default('lead');    // lead | qualifying | pricing | submitted | won | lost | on_hold
                $t->decimal('value', 12, 2)->nullable();
                $t->unsignedTinyInteger('probability')->nullable();
                $t->date('return_date')->nullable();          // tender return / quote due
                $t->date('decision_date')->nullable();
                $t->string('lost_reason', 190)->nullable();
                $t->text('notes')->nullable();
                $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('closed_at')->nullable();
                $t->string('dataverse_id', 36)->nullable()->index();
                $t->timestamps();
                $t->index(['account_id', 'stage']);
            });
        }

        if (! Schema::hasTable('crm_activities')) {
            Schema::create('crm_activities', function (Blueprint $t) {
                $t->id();
                $t->foreignId('account_id')->constrained()->cascadeOnDelete();
                $t->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
                $t->string('type', 20);                        // note | call | meeting | email
                $t->string('subject', 190)->nullable();
                $t->text('body')->nullable();
                $t->dateTime('occurred_at');
                $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $t->string('outlook_message_id')->nullable();
                $t->string('outlook_link', 500)->nullable();
                $t->string('dataverse_id', 36)->nullable()->index();
                $t->timestamps();
                $t->index(['account_id', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $t) {
                $t->id();
                $t->foreignId('account_id')->constrained()->cascadeOnDelete();
                $t->string('title', 255);
                $t->text('notes')->nullable();
                $t->date('due_on')->nullable();
                $t->string('status', 12)->default('open');     // open | done
                $t->timestamp('completed_at')->nullable();
                $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
                // Microsoft To Do: which person's list holds it, its id there, and the last change seen.
                $t->foreignId('todo_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('todo_task_id')->nullable()->index();
                $t->timestamp('todo_modified_at')->nullable();
                $t->boolean('todo_pending')->default(false);
                $t->string('todo_error', 255)->nullable();
                $t->string('dataverse_id', 36)->nullable()->index();
                $t->timestamps();
                $t->index(['account_id', 'status', 'assigned_to']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('contacts');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['microsoft_refresh_token', 'microsoft_connected_at', 'todo_list_id', 'todo_synced_at']);
        });
        Schema::table('organisations', function (Blueprint $t) {
            $t->dropForeign(['owner_id']);
            $t->dropColumn(['phone', 'email', 'website', 'postcode', 'sector', 'owner_id', 'dataverse_id']);
        });
    }
};
