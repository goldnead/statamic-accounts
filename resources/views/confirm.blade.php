@extends('accounts::layout')

@section('title', __('accounts::messages.confirm_title'))

@section('content')
    <h1>{{ __('accounts::messages.confirm_title') }}</h1>

    @if ($method === 'passkey')
        <p>{{ __('accounts::messages.confirm_passkey_unsupported') }}</p>
    @else
        <p>
            @if ($method === 'verification_code')
                {{ __('accounts::messages.confirm_intro_code', ['email' => $email]) }}
            @else
                {{ __('accounts::messages.confirm_intro_password') }}
            @endif
            @if ($action === 'delete')
                {{ __('accounts::messages.confirm_then_delete') }}
            @elseif ($action === 'change_email')
                {{ __('accounts::messages.confirm_then_change_email') }}
            @endif
        </p>

        @if ($status)
            <p class="status" role="status">{{ $status }}</p>
        @endif

        <form method="POST" action="{{ $submit_url }}">
            @csrf
            @if ($method === 'verification_code')
                <label class="field">
                    <span>{{ __('accounts::messages.confirm_code_label') }}</span>
                    <input type="text" name="verification_code" inputmode="numeric" autocomplete="one-time-code" required autofocus @if ($error) aria-invalid="true" aria-describedby="confirm-error" @endif>
                </label>
            @else
                <label class="field">
                    <span>{{ __('accounts::messages.confirm_password_label') }}</span>
                    <input type="password" name="password" autocomplete="current-password" required autofocus @if ($error) aria-invalid="true" aria-describedby="confirm-error" @endif>
                </label>
            @endif

            @if ($error)
                <p class="error" id="confirm-error" role="alert">{{ $error }}</p>
            @endif

            <button type="submit" class="btn">{{ __('accounts::messages.confirm_submit') }}</button>
        </form>

        <p class="muted">
            @if ($method === 'verification_code')
                <a href="{{ $resend_url }}">{{ __('accounts::messages.confirm_resend') }}</a>
            @endif
            <a href="{{ $cancel_url }}">{{ __('accounts::messages.confirm_cancel') }}</a>
        </p>
    @endif
@endsection
