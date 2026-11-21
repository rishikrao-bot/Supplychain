<?php

namespace App\Http\Controllers\Crm;

use App\Models\Contact;
use App\Models\Organisation;
use App\Services\Activity;
use Illuminate\Http\Request;

class ContactController extends CrmController
{
    public function index(Request $request)
    {
        $this->guard($request);
        $q = trim((string) $request->query('q'));
        $contacts = Contact::with('organisation')
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('first_name', 'like', "%$q%")->orWhere('last_name', 'like', "%$q%")
                ->orWhere('email', 'like', "%$q%")->orWhere('job_title', 'like', "%$q%")
                ->orWhereHas('organisation', fn ($o) => $o->where('name', 'like', "%$q%"))))
            ->orderBy('first_name')->orderBy('last_name')->paginate(50)->withQueryString();

        return view('crm.contacts.index', compact('contacts', 'q'));
    }

    public function show(Request $request, Contact $contact)
    {
        $this->guard($request);
        $contact->load(['organisation', 'owner', 'opportunities', 'tasks' => fn ($t) => $t->with('assignee')->orderBy('status')->orderBy('due_on')]);

        return view('crm.contacts.show', [
            'contact' => $contact,
            'activities' => $contact->activities()->with(['user', 'opportunity'])->limit(100)->get(),
            'emails' => $this->emails($request, [$contact->email]),
            'staff' => $this->staff(),
        ]);
    }

    private function companies()
    {
        return Organisation::where('type', '!=', Organisation::COMPASS)->orderBy('name')->get(['id', 'name', 'type']);
    }

    public function create(Request $request)
    {
        $this->guard($request);

        return view('crm.contacts.form', ['contact' => new Contact(['organisation_id' => $request->integer('company') ?: null, 'owner_id' => $request->user()->id]),
            'companies' => $this->companies(), 'staff' => $this->staff()]);
    }

    public function edit(Request $request, Contact $contact)
    {
        $this->guard($request);

        return view('crm.contacts.form', ['contact' => $contact, 'companies' => $this->companies(), 'staff' => $this->staff()]);
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'organisation_id' => $this->exists($request, 'organisations'),
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'owner_id' => $this->staffRule(),
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $contact = Contact::create($this->data($request));
        Activity::log('crm.contact_created', "{$request->user()->name} added contact {$contact->name()}.", $contact);

        return redirect()->route('contacts.show', $contact)->with('status', "{$contact->name()} added.");
    }

    public function update(Request $request, Contact $contact)
    {
        $this->guard($request);
        $contact->update($this->data($request));

        return redirect()->route('contacts.show', $contact)->with('status', "{$contact->name()} saved.");
    }
}
