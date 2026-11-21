<?php

use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MicrosoftLoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Crm\ActivityController;
use App\Http\Controllers\Crm\CompanyController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\OpportunityController;
use App\Http\Controllers\Crm\TaskController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IntegrationsController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkOrderReportController;
use App\Http\Controllers\Setup\CompetencyController;
use App\Http\Controllers\Setup\ContractorController;
use App\Http\Controllers\Setup\RulesController;
use App\Http\Controllers\Setup\SiteController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1');
});
// Microsoft sign-in, and "Connect Outlook & To Do" for people already logged in.
Route::get('/login/microsoft', [MicrosoftLoginController::class, 'redirect'])->middleware('throttle:20,1')->name('login.microsoft');
Route::get('/login/microsoft/callback', [MicrosoftLoginController::class, 'callback'])->middleware('throttle:20,1')->name('login.microsoft.callback');
Route::get('/invite/{token}', [InviteController::class, 'show'])->name('invite.show');
Route::post('/invite/{token}', [InviteController::class, 'accept'])->middleware('throttle:10,1')->name('invite.accept');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::get('/two-factor', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor', [TwoFactorController::class, 'verify'])->middleware('throttle:20,1')->name('two-factor.verify');
});

Route::middleware(['auth', 'secure'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::post('/users/{user}/resend', [UserController::class, 'resendInvite'])->name('users.resend');
    Route::post('/users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->name('users.reset2fa');
    Route::post('/organisations', [OrganisationController::class, 'store'])->name('organisations.store');
    Route::get('/jobs/{workOrder}/completion-report.pdf', WorkOrderReportController::class)->name('jobs.report');

    // Set-up data (Compass admin and helpdesk)
    Route::resource('sites', SiteController::class)->except(['show', 'destroy']);
    Route::resource('contractors', ContractorController::class)->except(['show', 'destroy'])->parameters(['contractors' => 'contractor']);
    Route::post('/contractors/{contractor}/suspend', [ContractorController::class, 'suspend'])->name('contractors.suspend');
    Route::post('/contractors/{contractor}/documents', [ContractorController::class, 'storeDocument'])->name('contractors.documents.store');
    Route::get('/documents/{document}/download', [ContractorController::class, 'downloadDocument'])->name('documents.download');
    Route::get('/competencies', [CompetencyController::class, 'index'])->name('competencies.index');
    Route::post('/competencies', [CompetencyController::class, 'store'])->name('competencies.store');
    Route::get('/rates-and-rules', [RulesController::class, 'index'])->name('rules.index');
    Route::patch('/rates-and-rules/{setting}', [RulesController::class, 'update'])->name('rules.update');

    // CRM (Compass office staff)
    Route::resource('crm/companies', CompanyController::class)->except(['destroy'])->names('companies')->parameters(['companies' => 'company']);
    Route::resource('crm/contacts', ContactController::class)->except(['destroy'])->names('contacts');
    Route::resource('crm/pipeline', OpportunityController::class)->except(['destroy'])->names('pipeline')->parameters(['pipeline' => 'opportunity']);
    Route::post('/crm/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::post('/crm/activities/email', [ActivityController::class, 'storeEmail'])->name('activities.email');
    Route::delete('/crm/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('/tasks/sync', [TaskController::class, 'sync'])->middleware('throttle:10,1')->name('tasks.sync');
    Route::post('/tasks/disconnect', [TaskController::class, 'disconnect'])->name('tasks.disconnect');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('/tasks/{task}/toggle', [TaskController::class, 'toggle'])->name('tasks.toggle');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Microsoft 365 connections (Compass admins)
    Route::get('/microsoft-365', [IntegrationsController::class, 'index'])->name('integrations.index');
    Route::post('/microsoft-365/test', [IntegrationsController::class, 'test'])->middleware('throttle:10,1')->name('integrations.test');
    Route::post('/microsoft-365/sync', [IntegrationsController::class, 'sync'])->middleware('throttle:5,1')->name('integrations.sync');
});
