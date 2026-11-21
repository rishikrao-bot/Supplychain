<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Microsoft\Outlook;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Throwable;

/** Shared bits for the CRM screens: Compass office staff only. */
abstract class CrmController extends Controller
{
    protected function guard(Request $request): void
    {
        abort_unless($request->user()->canUseCrm(), 403);
    }

    /** Compass staff who can own records and be given tasks. */
    protected function staff(): Collection
    {
        return User::where('status', User::ACTIVE)->whereIn('role', [Roles::ADMIN, Roles::HELPDESK, Roles::FINANCE])
            ->whereHas('organisation', fn ($q) => $q->where('type', Organisation::COMPASS))->orderBy('name')->get();
    }

    /** Validation rule: an id in this account's table. */
    protected function exists(Request $request, string $table): array
    {
        return ['nullable', 'integer', Rule::exists($table, 'id')->where('account_id', $request->user()->account_id)];
    }

    protected function staffRule(): array
    {
        return ['nullable', 'integer', Rule::in($this->staff()->pluck('id')->all())];
    }

    /** The viewer's Outlook emails with these addresses: [list, error]. */
    protected function emails(Request $request, array $addresses): array
    {
        $me = $request->user();
        if (! $me->hasMicrosoft()) {
            return [null, null];
        }
        try {
            return [Outlook::emailsWith($me, $addresses), null];
        } catch (Throwable $e) {
            return [[], $e->getMessage()];
        }
    }

}
