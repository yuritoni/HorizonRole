@extends('layouts.app')

@section('title', 'Edit '.$record->code.' - '.config('app.name'))

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">{{ $config['group'] }}</p>
            <h1>Edit {{ $record->code }}</h1>
            <p>Perbarui data {{ strtolower($config['label']) }}.</p>
        </div>
    </header>

    <section class="panel form-panel">
        @include('records.form', [
            'action' => route('records.update', [$module, $record]),
            'method' => 'PUT',
        ])
    </section>
@endsection
