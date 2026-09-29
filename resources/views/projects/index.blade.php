@extends('layouts.app')
@section('breadcrumb', 'Proyectos')
@section('content')
<div class="page-heading"><div><p class="eyebrow">CATÁLOGO</p><h1>Proyectos</h1><p class="muted">Contexto y reglas para cada entorno de desarrollo.</p></div><a class="button primary" href="{{ route('projects.create') }}">+ Nuevo proyecto</a></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Proyecto</th><th>Cliente</th><th>Tipo / Stack</th><th>Worker preferido</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($projects as $project)<tr><td><b>{{ $project->name }}</b><small class="block">{{ $project->slug }}</small></td><td>{{ $project->client ?? '—' }}</td><td>{{ $project->type }}<small class="block">{{ $project->stack }}</small></td><td>{{ $project->preferredWorker?->name ?? '—' }}</td><td><span class="badge {{ strtolower($project->status) }}">{{ $project->status }}</span></td><td><a href="{{ route('projects.edit', $project) }}">Editar →</a></td></tr>@empty<tr><td colspan="6" class="empty">Sin proyectos.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $projects->links() }}</div></section>
@endsection
