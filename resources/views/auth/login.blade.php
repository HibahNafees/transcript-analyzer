@extends('layout')

@section('title', 'Log in · Transcript Analyzer')

@section('content')
    <h1>Log in</h1>
    <p class="muted">Your transcripts are private to your account.</p>

    <div class="card">
        <form class="auth" method="POST" action="{{ route('login') }}">
            @csrf
            <label>
                <span class="label">Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </label>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
            <label>
                <span class="label">Password</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="small"><input type="checkbox" name="remember"> Remember me</label>
            <button type="submit">Log in</button>
        </form>
    </div>
    <p class="muted small">Have a class invite code? <a href="{{ route('register') }}">Create an account</a></p>
@endsection
