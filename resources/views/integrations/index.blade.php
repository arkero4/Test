@extends('layouts.app')
@section('breadcrumb', 'Integraciones')
@section('content')
<div class="page-heading"><div><p class="eyebrow">CONEXIONES</p><h1>Integraciones</h1><p class="muted">Estado de las credenciales de ingreso y actividad recibida por la API.</p></div></div>
<section class="panel"><div class="panel-head"><h2>Clientes de ingesta</h2></div><div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Credencial</th><th>Última llamada autenticada</th><th>Requerimientos ingresados</th></tr></thead><tbody>
@forelse($clients as $client)
<tr><td><b>{{ $client->name }}</b><small class="block">{{ $client->slug }}</small></td><td><span class="badge {{ $client->status === 'ACTIVE' && $client->token_hash ? 'online' : 'offline' }}">{{ $client->status === 'ACTIVE' && $client->token_hash ? 'Emitida' : 'Inactiva' }}</span></td><td>{{ $client->last_used_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td><td>{{ $client->requirements_received }}</td></tr>
@empty
<tr><td colspan="4" class="empty">Sin clientes de ingesta configurados.</td></tr>
@endforelse
</tbody></table></div><div class="panel-body"><p class="muted">Una credencial emitida no significa que Dot ya esté conectado. La primera llamada y los requerimientos ingresados confirman actividad real.</p></div></section>
@endsection
