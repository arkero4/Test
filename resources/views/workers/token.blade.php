@extends('layouts.app')
@section('breadcrumb', 'Workers / Token emitido')
@section('content')
<div class="page-heading"><div><p class="eyebrow">TOKEN DE WORKER</p><h1>{{ $worker->name }}</h1><p class="muted">Cópialo ahora y guárdalo solo en la máquina worker. No volverá a mostrarse.</p></div><a class="button" href="{{ route('workers.edit', $worker) }}">Volver al worker</a></div>
<section class="panel"><div class="panel-body"><div class="code">{{ $token }}</div><p class="muted">El token anterior quedó revocado. UUID: {{ $worker->uuid }}</p></div></section>
@endsection
