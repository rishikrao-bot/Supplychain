<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Opportunity;
use App\Models\Setting;
use App\Models\Task;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $activity = $user->isOperatorAdmin() ? ActivityLog::with('user')->latest('created_at')->limit(15)->get() : collect();
        $onHold = $user->isOperator() ? Setting::whereIn('status', ['on_hold', 'proposed'])->orderBy('status')->get() : collect();

        $myTasks = $user->canUseCrm() ? Task::with(['organisation', 'contact', 'opportunity'])->where('assigned_to', $user->id)->where('status', Task::OPEN)
            ->orderByRaw('due_on is null')->orderBy('due_on')->limit(8)->get() : collect();
        $deadlines = $user->canUseCrm() ? Opportunity::with('organisation')->whereIn('stage', Opportunity::openStages())->whereNotNull('return_date')
            ->where('return_date', '>=', now()->toDateString())->where('return_date', '<=', now()->addDays(21)->toDateString())->orderBy('return_date')->limit(8)->get() : collect();

        return view('home', compact('user', 'activity', 'onHold', 'myTasks', 'deadlines'));
    }
}
