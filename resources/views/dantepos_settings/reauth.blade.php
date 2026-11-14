@extends('layouts.app')
@section('title', 'Confirm your password')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Confirm your password
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Payment settings are protected</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary'])
        @if (session('dantepos_error'))
            <div class="alert alert-danger">{{ session('dantepos_error') }}</div>
        @endif
        <form method="POST" action="{{ route('dante.reauth.check') }}" style="max-width:420px;" autocomplete="off">
            @csrf
            <p class="text-muted">
                These settings control where customer payments go. Enter your own password to continue.
                You will not be asked again for {{ (int) config('dante.reauth_minutes', 15) }} minutes.
            </p>
            <div class="form-group {{ $errors->has('password') ? 'has-error' : '' }}">
                <label>Your password</label>
                <input type="password" name="password" class="form-control" required autofocus autocomplete="current-password">
                @if ($errors->has('password'))
                    <span class="help-block">{{ $errors->first('password') }}</span>
                @endif
            </div>
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">Confirm</button>
        </form>
    @endcomponent
</section>
@endsection
