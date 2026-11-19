<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $activity = $user->isCompassAdmin() ? ActivityLog::with('user')->latest('created_at')->limit(15)->get() : collect();
        $onHold = $user->isCompass() ? Setting::whereIn('status', ['on_hold', 'proposed'])->orderBy('status')->get() : collect();

        return view('home', compact('user', 'activity', 'onHold'));
    }
}
