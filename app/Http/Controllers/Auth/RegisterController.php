<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['open' => filled(config('auth.invite_code'))]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Every upload spends Fire credit, so only people with the class invite code can sign up.
        $code = (string) config('auth.invite_code');
        if ($code === '' || ! hash_equals($code, $validated['invite_code'])) {
            throw ValidationException::withMessages(['invite_code' => 'That invite code is not valid.']);
        }

        $user = User::create($validated);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('transcripts.index');
    }
}
