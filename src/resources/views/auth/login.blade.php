@extends('layouts.app')
@section('title', 'Log in')

@section('content')
    <div class="card">
        <h1>Log in</h1>
        <p class="sub">Visitor Management dashboard</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field">
                <label style="display:flex; align-items:center; gap:6px; color:var(--ink);">
                    <input type="checkbox" name="remember" style="width:auto;"> Remember me
                </label>
            </div>
            @error('email')<div class="error">{{ $message }}</div>@enderror
            <button class="btn" type="submit" style="width:100%; margin-top:8px;">Log in</button>
        </form>
    </div>
@endsection
