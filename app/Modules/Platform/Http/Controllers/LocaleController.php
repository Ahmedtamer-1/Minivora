<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, config('minivora.locales'), true), 404);

        $request->session()->put('locale', $locale);

        /** @var User|null $user */
        $user = $request->user();
        $user?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
