<?php

namespace App\Http\Controllers\Crm;

use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Services\Microsoft\Outlook;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/** Notes, calls and meetings, and Outlook emails saved to a record. */
class ActivityController extends CrmController
{
    private function links(Request $request): array
    {
        $data = $request->validate([
            'organisation_id' => $this->exists($request, 'organisations'),
            'contact_id' => $this->exists($request, 'contacts'),
            'opportunity_id' => $this->exists($request, 'opportunities'),
        ]);
        if (! empty($data['opportunity_id'])) {
            $opp = Opportunity::find($data['opportunity_id']);
            $data['organisation_id'] ??= $opp->organisation_id;
            $data['contact_id'] ??= $opp->contact_id;
        }
        if (! empty($data['contact_id'])) {
            $data['organisation_id'] ??= Contact::find($data['contact_id'])->organisation_id;
        }
        abort_if(empty($data['organisation_id']) && empty($data['contact_id']) && empty($data['opportunity_id']), 422);

        return $data;
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['note', 'call', 'meeting'])],
            'subject' => ['nullable', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
        ]);
        CrmActivity::create($data + $this->links($request) + ['occurred_at' => $data['occurred_at'] ?? now(), 'user_id' => $request->user()->id]);

        return back()->with('status', CrmActivity::TYPES[$data['type']].' saved.');
    }

    public function storeEmail(Request $request)
    {
        $this->guard($request);
        $request->validate(['message_id' => ['required', 'string', 'max:500']]);
        $links = $this->links($request);
        $me = $request->user();
        if (CrmActivity::where('outlook_message_id', $request->input('message_id'))->where($links)->exists()) {
            return back()->with('status', 'That email is already saved here.');
        }
        try {
            $m = Outlook::message($me, $request->input('message_id'));
        } catch (Throwable $e) {
            return back()->withErrors(['microsoft' => $e->getMessage()]);
        }
        CrmActivity::create($links + [
            'type' => 'email', 'subject' => mb_substr($m['subject'], 0, 190),
            'body' => "From: {$m['from']}\nTo: {$m['to']}\n\n{$m['preview']}",
            'occurred_at' => $m['at'] ?? now(), 'user_id' => $me->id,
            'outlook_message_id' => $m['id'], 'outlook_link' => $m['link'] ? mb_substr($m['link'], 0, 500) : null,
        ]);

        return back()->with('status', 'Email saved to the record.');
    }

    public function destroy(Request $request, CrmActivity $activity)
    {
        $this->guard($request);
        abort_unless($activity->user_id === $request->user()->id || $request->user()->isCompassAdmin(), 403);
        $activity->delete();

        return back()->with('status', 'Removed.');
    }
}
