<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core tables for the Compass MEP reactive maintenance portal (stage 1).
 *
 * Every business table carries account_id so more than one company can run on the
 * same system later with their data kept apart. Compass MEP is the first account.
 *
 * Note: Laravel already uses a table called "jobs" for its queue, so maintenance
 * jobs are stored in "work_orders".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('brand_colour', 7)->nullable();
            $t->string('accent_colour', 7)->nullable();
            $t->string('logo_path')->nullable();
            $t->timestamps();
        });

        // Compass itself, each client (e.g. Wates) and each supply chain partner.
        Schema::create('organisations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->string('type'); // compass | client | partner
            $t->string('name');
            $t->string('contact_name')->nullable();
            $t->string('contact_phone')->nullable();
            $t->string('contact_email')->nullable();
            $t->string('address')->nullable();
            // Partners
            $t->string('partner_type')->nullable(); // subcontractor | specialist
            $t->json('zones')->nullable();           // ["A","B"]
            $t->decimal('job_limit', 10, 2)->nullable();
            $t->boolean('vat_registered')->default(true);
            $t->string('cis_status')->nullable();    // on hold: CIS hidden for now
            $t->json('rates')->nullable();           // partner's own rates: normal, ooh, sun, callout
            $t->timestamp('suspended_at')->nullable();
            // Clients: payment terms etc. (on hold until agreed at onboarding)
            $t->json('settings')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['account_id', 'type']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $t->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
        });

        // A client's schedule of rates, versioned so old jobs keep the rates they were priced on.
        Schema::create('rate_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('organisations')->cascadeOnDelete();
            $t->string('name');
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->string('status')->default('active'); // draft | active | retired
            $t->timestamps();
        });
        Schema::create('rate_card_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rate_card_id')->constrained()->cascadeOnDelete();
            $t->string('code');   // e.g. labour.building.normal, callout.emergency, zone.b
            $t->string('label');
            $t->decimal('amount', 10, 2)->nullable(); // null = TBC
            $t->string('unit')->nullable();           // per_hour | per_visit | percent | fixed
            $t->unique(['rate_card_id', 'code']);
        });

        Schema::create('contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('organisations')->cascadeOnDelete();
            $t->foreignId('rate_card_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('end_client')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('sites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('address')->nullable();
            $t->string('postcode', 12)->nullable();
            $t->string('zone', 1)->default('A'); // A | B | C
            $t->string('site_type')->nullable();
            $t->string('contact_name')->nullable();
            $t->string('contact_phone')->nullable();
            $t->text('access_notes')->nullable();
            $t->text('asbestos_notes')->nullable();
            $t->decimal('latitude', 9, 6)->nullable();
            $t->decimal('longitude', 9, 6)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('competency_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->unsignedSmallInteger('sort')->default(0);
        });
        Schema::create('competencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('competency_group_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('certificate_required')->nullable(); // e.g. "Gas Safe", "BAFE"
            $t->timestamps();
        });
        Schema::create('competency_organisation', function (Blueprint $t) {
            $t->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $t->primary(['competency_id', 'organisation_id']);
        });

        // Compliance documents for an organisation, or for one person (CSCS, DBS, asbestos awareness).
        Schema::create('compliance_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->boolean('blocks_offers')->default(false);
            $t->date('expires_on')->nullable();
            $t->string('file_path')->nullable();
            $t->string('status')->default('pending'); // pending | approved | rejected
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        // Running numbers per account (Q-/P- job numbers, quote numbers).
        Schema::create('number_sequences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->unsignedBigInteger('next_value')->default(1);
            $t->unique(['account_id', 'name']);
        });

        // A maintenance job. Shown as Q-{number} until the client approves it, then P-{number}.
        Schema::create('work_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('number');
            $t->foreignId('client_id')->constrained('organisations');
            $t->foreignId('contract_id')->constrained();
            $t->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $t->string('client_ref')->nullable();      // e.g. Wates job number
            $t->string('client_order_no')->nullable(); // PO number given on approval
            $t->string('source');                     // email | phone | client_portal | return_visit
            $t->string('priority');                   // emergency | urgent | routine | planned
            $t->string('rate_band')->default('normal'); // normal | ooh | sun
            $t->string('location')->nullable();
            $t->text('description');
            $t->decimal('nte', 10, 2)->nullable();
            $t->decimal('est_materials', 10, 2)->default(0);
            $t->string('specialist_category')->nullable();
            $t->decimal('specialist_cost', 10, 2)->nullable();
            $t->string('status')->default('awaiting'); // awaiting|approved|offered|slots|booked|on_site|review|hold|complete|cancelled
            $t->string('appointment_window')->nullable();
            $t->foreignId('assigned_org_id')->nullable()->constrained('organisations')->nullOnDelete();
            $t->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('estimate_total', 10, 2)->nullable();
            $t->json('estimate_lines')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('approved_amount', 10, 2)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('received_at');
            $t->timestamps();
            $t->unique(['account_id', 'number']);
            $t->index(['account_id', 'status']);
        });

        // One or more trades per job, each with its own competency and estimated hours.
        Schema::create('work_order_trades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->string('trade'); // building | specialist | lift | service
            $t->foreignId('competency_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('est_hours', 6, 2)->default(0);
            $t->boolean('is_primary')->default(false);
        });

        // Files attached to anything: order attachments, visit photos, certificates.
        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->morphs('attachable');
            $t->string('kind')->default('file'); // file | photo_before | photo_after | evidence | certificate | signature
            $t->string('path');
            $t->string('original_name');
            $t->string('mime')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->json('meta')->nullable(); // e.g. GPS and timestamp for photos
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Offers to partners: one at a time, or to all eligible partners for emergencies.
        Schema::create('job_offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $t->string('mode');   // single | broadcast
            $t->string('status')->default('offered'); // offered|accepted|declined|expired|withdrawn
            $t->timestamp('deadline_at')->nullable();
            $t->timestamp('responded_at')->nullable();
            $t->string('reason')->nullable();
            $t->timestamps();
        });

        // Availability requests: every eligible partner offers up to 3 slots.
        Schema::create('availability_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->timestamp('deadline_at')->nullable();
            $t->string('status')->default('open'); // open | booked | cancelled
            $t->timestamps();
        });
        Schema::create('availability_replies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('availability_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('waiting'); // waiting | offered | not_available
            $t->timestamp('responded_at')->nullable();
        });
        Schema::create('availability_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('availability_reply_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->string('window');
            $t->boolean('client_preferred')->default(false);
            $t->boolean('booked')->default(false);
        });

        // Engineer visit and its report.
        Schema::create('visits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('arrived_at')->nullable();
            $t->timestamp('departed_at')->nullable();
            $t->decimal('arrival_lat', 9, 6)->nullable();
            $t->decimal('arrival_lng', 9, 6)->nullable();
            $t->json('safety_checklist')->nullable();
            $t->text('notes')->nullable();
            $t->string('outcome')->nullable(); // complete | further | parts | noaccess | asbestos
            $t->text('outcome_detail')->nullable();
            $t->unsignedSmallInteger('calls_to_site')->nullable();
            $t->boolean('within_window')->nullable();
            $t->string('signed_by')->nullable();
            $t->decimal('expenses', 10, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('visit_materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $t->string('description');
            $t->decimal('cost', 10, 2);
        });

        // No access, further works, parts, asbestos, over estimate: sent to the client for a decision.
        Schema::create('job_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->string('title');
            $t->text('detail')->nullable();
            $t->string('charge_note')->nullable();
            $t->string('question')->nullable();
            $t->json('actions')->nullable();
            $t->string('status')->default('open'); // open | closed
            $t->string('decision')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });

        // Further-works and inspection quotes.
        Schema::create('quotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('number');
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $t->text('description');
            $t->decimal('value', 10, 2)->nullable();
            $t->decimal('inspection_fee', 10, 2)->default(0);
            $t->string('status')->default('awaiting'); // awaiting | ordered | declined
            $t->timestamp('ordered_at')->nullable();
            $t->timestamps();
            $t->unique(['account_id', 'number']);
        });

        Schema::create('valuations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->json('lines');
            $t->decimal('total', 10, 2);
            $t->timestamp('issued_at')->nullable();
            $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Everything that happens, for the audit trail.
        Schema::create('activity_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->nullableMorphs('subject');
            $t->string('action');
            $t->text('message');
            $t->json('data')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        // Rules and settings. status marks items still being agreed with the client.
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->json('value')->nullable();
            $t->string('status')->default('agreed'); // agreed | proposed | on_hold
            $t->string('note')->nullable();
            $t->timestamps();
            $t->unique(['account_id', 'key']);
        });
    }

    public function down(): void
    {
        foreach (['settings', 'activity_log', 'valuations', 'quotes', 'job_exceptions', 'visit_materials', 'visits',
            'availability_slots', 'availability_replies', 'availability_requests', 'job_offers', 'attachments',
            'work_order_trades', 'work_orders', 'number_sequences', 'compliance_documents', 'competency_organisation',
            'competencies', 'competency_groups', 'sites', 'contracts', 'rate_card_items', 'rate_cards'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['account_id']);
            $t->dropForeign(['organisation_id']);
        });
        Schema::dropIfExists('organisations');
        Schema::dropIfExists('accounts');
    }
};
