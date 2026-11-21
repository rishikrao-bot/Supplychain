<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Every user belongs to one company account (the operator first) and one organisation
            // inside it: the operator itself, a client, or a supply chain partner.
            // The foreign keys are added in the domain migration once those tables exist.
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('organisation_id')->index();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            // Operator: admin, helpdesk, finance, engineer. Client: client_admin, approver, viewer.
            // Partner: partner_manager, site_engineer.
            $table->string('role');
            $table->string('job_title')->nullable();
            // invited -> active -> disabled
            $table->string('status')->default('invited');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            // Two-step login (authenticator app). Secret is stored encrypted.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            // Invites: only a hash of the token is stored.
            $table->string('invite_token_hash', 64)->nullable()->index();
            $table->timestamp('invited_at')->nullable();
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
