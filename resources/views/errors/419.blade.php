@extends('errors.layout')
@php
    // previous() can fall back to the Referer header; only link back within this app.
    $back = url()->previous();
    $back = str_starts_with($back, url('/').'/') && $back !== url()->current() ? $back : url('/admin');
@endphp
@section('code', '419')
@section('accent', '#b45309')
@section('level', '4')
@section('title', 'Your session expired')
@section('message', 'For safety, forms expire after a period of inactivity. Reload the page and submit again; nothing was saved.')
@section('primary_action')
    <a class="btn primary" href="{{ $back }}">Reload and try again</a>
@endsection
