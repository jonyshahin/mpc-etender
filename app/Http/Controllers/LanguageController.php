<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'language' => ['required', 'in:en,ar,ku'],
        ]);

        $lang = $request->input('language');

        // Update user or vendor preference
        if ($request->user()) {
            $request->user()->update(['language_pref' => $lang]);
        } elseif ($request->user('vendor')) {
            $request->user('vendor')->update(['language_pref' => $lang]);
        }

        // Set session locale
        session(['locale' => $lang]);

        return back();
    }
}
