<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', ['settings' => Setting::current()]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        Setting::current()->update($request->settingsData());

        return redirect()->route('admin.ajustes.edit')->with('success', 'Se guardaron los ajustes.');
    }
}
