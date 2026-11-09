@extends('errors.layout')
@php($role = auth()->user()?->primaryRole())
@section('code', '403')
@section('accent', '#b45309')
@section('level', '5')
@section('title', 'This area is outside your role')
@section('message', 'Access here is limited by the maker-checker rules, so not every role can open every screen. Nothing was changed.')
@section('extra')
    @if ($role)
        <div class="who">You are signed in as <strong>{{ $role->getLabel() }}</strong>.
            @switch($role)
                @case(\App\Enums\UserRole::Operator)
                    Operators record discharges and loadings and request corrections. Approvals, vessels and the audit log need a Supervisor.
                    @break
                @case(\App\Enums\UserRole::Supervisor)
                    Tanks, commodity profiles, users and terminal settings are managed by an Admin.
                    @break
                @default
                    If you believe you should have access, check the user's role under Administration → Users.
            @endswitch
        </div>
    @endif
@endsection
