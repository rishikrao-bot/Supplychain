<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\RateCard;
use App\Models\Setting;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RulesController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isOperatorStaff(), 403);

        return view('setup.rules', [
            'cards' => RateCard::with(['items', 'client'])->orderBy('name')->get(),
            'settings' => Setting::orderByRaw("CASE status WHEN 'on_hold' THEN 0 WHEN 'proposed' THEN 1 ELSE 2 END")->orderBy('key')->get(),
            'canEdit' => $request->user()->isOperatorAdmin(),
        ]);
    }

    public function update(Request $request, Setting $setting)
    {
        abort_unless($request->user()->isOperatorAdmin(), 403);
        $data = $request->validate(['value' => ['nullable', 'string', 'max:120'], 'status' => ['required', Rule::in(['agreed', 'proposed', 'on_hold'])]]);
        $old = $setting->value;
        $raw = $data['value'] ?? '';
        $value = is_bool($old) ? in_array(strtolower($raw), ['1', 'yes', 'true', 'on'], true) : (is_numeric($raw) && (is_int($old) || is_float($old)) ? $raw + 0 : $raw);
        $setting->update(['value' => $value, 'status' => $data['status']]);
        Activity::log('setting.updated', "{$request->user()->name} changed {$setting->key} to ".json_encode($value)." ({$data['status']}).", $setting);

        return back()->with('status', 'Rule saved.');
    }
}
