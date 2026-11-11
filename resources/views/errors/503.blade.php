@extends('errors.layout')
@section('code', '503')
@section('heading', 'Back shortly')
@section('message', 'We’re doing some quick maintenance. Please check back in a few minutes.')
@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Try again</a>
@endsection
