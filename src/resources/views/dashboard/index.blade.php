@extends('layouts.app')
@section('title', 'Visits')

@section('content')
    <div class="row">
        <div>
            <h1>Visitor log</h1>
            <p class="sub">
                @if($user->isTenantAdmin())
                    Arrivals for your office.
                @elseif($user->isBuildingManager())
                    Building-wide arrivals.
                @else
                    All arrivals across buildings.
                @endif
            </p>
        </div>
        <a class="btn" href="{{ route('dashboard.export') }}">Export CSV</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Arrived</th>
                @unless($user->isTenantAdmin())<th>Tenant</th>@endunless
                <th>Visitor phone</th>
                <th>Purpose</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($visits as $visit)
                <tr>
                    <td>{{ optional($visit->checked_in_at)->format('Y-m-d H:i') }}</td>
                    @unless($user->isTenantAdmin())<td>{{ $visit->tenant?->name }}</td>@endunless
                    <td>{{ $visit->visitor_phone }}</td>
                    <td>{{ $visit->purpose }}</td>
                    <td><span class="pill">{{ str_replace('_',' ', $visit->status->value) }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">No visits logged yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">
        {{ $visits->links() }}
    </div>
@endsection
