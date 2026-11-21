{{-- "Same days every week" shortcut. Needs $projectsList (or $fixedProject) and $staffList (or $fixedStaff). --}}
<details class="panel add"><summary class="panel-h" style="border:0"><h2>Plan several weeks at once</h2><span class="btn sm">Open</span></summary>
<form method="post" action="{{ route('resources.fill') }}" class="panel-b">@csrf
  <p class="small muted">Puts the same number of days in every week between the two dates. Use 0 to take someone off a project for those weeks.</p>
  <div class="grid g4">
    @isset($fixedStaff)<input type="hidden" name="staff_id" value="{{ $fixedStaff->id }}">@else
    <div class="field"><label for="f-staff">Person</label><select id="f-staff" name="staff_id" required>@foreach($staffList as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>@endisset
    @isset($fixedProject)<input type="hidden" name="project_id" value="{{ $fixedProject->id }}">@else
    <div class="field"><label for="f-project">Project</label><select id="f-project" name="project_id" required>@foreach($projectsList as $p)<option value="{{ $p->id }}">{{ $p->label() }}</option>@endforeach</select></div>@endisset
    <div class="field"><label for="f-days">Days a week</label><input id="f-days" type="number" name="days" min="0" max="7" step="0.5" value="1" required></div>
    <div class="field"><label for="f-from">From week</label><input id="f-from" type="date" name="from" value="{{ $thisWeek }}" required></div>
    <div class="field"><label for="f-to">To week</label><input id="f-to" type="date" name="to" value="{{ \Illuminate\Support\Carbon::parse($thisWeek)->addWeeks(11)->toDateString() }}" required></div>
  </div>
  <label class="small row"><input type="checkbox" name="overwrite" value="1" checked> Replace weeks that already have days on this project</label>
  <div class="row end"><button class="btn primary">Plan these weeks</button></div>
</form></details>
