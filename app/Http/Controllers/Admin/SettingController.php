<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        $settings = SystemSetting::orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group');

        return Inertia::render('admin/Settings/Index', [
            'settingGroups' => $settings,
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        // system_settings.value is NOT NULL, but ConvertEmptyStringsToNull turns a
        // cleared field into null, which the `nullable` rule happily passes. Coerce
        // back to '' or the update throws. The transaction stops a mid-loop failure
        // from leaving some settings written and the rest not.
        DB::transaction(function () use ($request) {
            foreach ($request->validated('settings') as $setting) {
                SystemSetting::where('key', $setting['key'])->update([
                    'value' => $setting['value'] ?? '',
                    'updated_at' => now(),
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings updated successfully.')]);

        return back();
    }
}
