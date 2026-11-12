@extends('layouts.app')

@section('title', 'Tambah '.$config['label'].' - '.config('app.name'))

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">{{ $config['group'] }}</p>
            <h1>Tambah {{ $config['label'] }}</h1>
            <p>Masukkan data baru untuk modul {{ strtolower($config['label']) }}.</p>
        </div>
    </header>

    <section class="panel form-panel">
        @include('records.form', [
            'action' => route('records.store', $module),
            'method' => 'POST',
        ])
    </section>
@endsection
