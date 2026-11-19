<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Quick add of a client or partner company so people can be invited to it. Full partner set-up comes with the contractors section. */
class OrganisationController extends Controller
{
    public function store(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->isCompassAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in([Organisation::CLIENT, Organisation::PARTNER])],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:190'],
        ]);
        $org = Organisation::create($data + ['account_id' => $actor->account_id]);
        Activity::log('organisation.created', "{$actor->name} added {$org->name} ({$org->type}).", $org);

        return redirect()->route('users.index', ['tab' => $org->type])->with('status', "{$org->name} added. You can now invite people to it.");
    }
}
