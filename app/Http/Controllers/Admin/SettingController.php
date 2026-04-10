<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
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
        foreach ($request->validated('settings') as $setting) {
            SystemSetting::where('key', $setting['key'])->update([
                'value' => $setting['value'],
                'updated_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => __('Settings updated successfully.')]);
    }
}
