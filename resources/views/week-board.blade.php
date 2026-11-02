@extends('layouts.app')

@section('title', 'Week Board - '.config('app.name'))

@section('content')
    <livewire:client-week-board />
@endsection
