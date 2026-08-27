@extends('admin.layout')
@section('content')
<h1>Revendications</h1>
<form><select name="status"><option value="">Tous statuts</option>@foreach(['pending','approved','rejected'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select><button>Filtrer</button></form>
<table><tr><th>Restaurant</th><th>Utilisateur</th><th>Date</th><th>Détails</th><th>Statut</th><th>Action</th></tr>@forelse($claims as $claim)<tr data-claim-id="{{ $claim->id }}"><td>{{ $claim->restaurant->name }}</td><td>{{ $claim->user->name }}</td><td>{{ $claim->submitted_at }}</td><td>{{ $claim->message }}</td><td>{{ $claim->status }}</td><td>@if($claim->status==='pending')<form method="post" action="{{ route('admin.claims.approve', $claim) }}" style="display:inline">@csrf @method('PATCH')<button>Approuver</button></form><form method="post" action="{{ route('admin.claims.reject', $claim) }}" style="display:inline">@csrf @method('PATCH')<input name="admin_note" aria-label="Note de refus"><button>Refuser</button></form>@endif</td></tr>@empty<tr><td colspan="6">Aucune revendication.</td></tr>@endforelse</table>{{ $claims->links() }}
@endsection
