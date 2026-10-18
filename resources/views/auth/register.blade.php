@extends('layout')

@section('title', 'Create an account · Transcript Analyzer')

@section('content')
    <h1>Create an account</h1>

    @unless ($open)
        <div class="notice warn">Registration is closed right now. Ask the site owner for an account.</div>
    @else
        <p class="muted">You need the invite code from your class to sign up.</p>

        <div class="card">
            <form class="auth" method="POST" action="{{ route('register') }}">
                @csrf
                <label>
                    <span class="label">Invite code</span>
                    <input type="text" name="invite_code" value="{{ old('invite_code') }}" required autofocus autocomplete="off">
                </label>
                @error('invite_code') <p class="field-error">{{ $message }}</p> @enderror
                <label>
                    <span class="label">Name</span>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name">
                </label>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
                <label>
                    <span class="label">Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                </label>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
                <label>
                    <span class="label">Password (at least 8 characters)</span>
                    <input type="password" name="password" required minlength="8" autocomplete="new-password">
                </label>
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
                <label>
                    <span class="label">Confirm password</span>
                    <input type="password" name="password_confirmation" required autocomplete="new-password">
                </label>
                <button type="submit">Create account</button>
            </form>
        </div>
    @endunless
    <p class="muted small">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
@endsection
