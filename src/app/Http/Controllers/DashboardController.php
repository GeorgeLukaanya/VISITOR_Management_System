<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Visit dashboard + CSV export.
 *
 * Scoping is automatic: the Visit global scope (Step 2) constrains every query
 * to what the authenticated user may see — a tenant admin sees only their
 * tenant's visits, a building manager their whole building, a platform admin
 * everything. The policy `viewAny`/`export` gates the actions themselves.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Visit::class);

        $visits = Visit::query()
            ->with('tenant')
            ->latest('checked_in_at')
            ->paginate(25);

        return view('dashboard.index', [
            'visits' => $visits,
            'user' => $request->user(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Visit::class);

        $filename = 'visits-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Visit ID', 'Tenant', 'Visitor Phone', 'Purpose',
                'Status', 'Checked In At', 'Checked Out At',
            ]);

            // chunkById keeps memory flat for large exports; the global scope
            // still applies so a tenant can only ever export their own rows.
            Visit::query()
                ->with('tenant')
                ->orderBy('id')
                ->chunkById(500, function ($visits) use ($out) {
                    foreach ($visits as $visit) {
                        fputcsv($out, [
                            $visit->id,
                            $visit->tenant?->name,
                            $visit->visitor_phone,
                            $visit->purpose,
                            $visit->status->value,
                            optional($visit->checked_in_at)->toDateTimeString(),
                            optional($visit->checked_out_at)->toDateTimeString(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
