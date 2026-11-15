<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FROMS - Driver &amp; Bus Assignment Sheet - {{ $selectedTripDate }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 24px; font: 13px/1.5 'Poppins', Arial, sans-serif; color: #15253c; }
        header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; border-bottom: 2px solid #18375c; padding-bottom: 15px; margin-bottom: 18px; }
        h1 { font-size: 21px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 0; }
        .muted { color: #52637b; }
        .toolbar { display: flex; gap: 10px; justify-content: flex-end; margin-bottom: 20px; }
        .toolbar button, .toolbar a { display: inline-flex; align-items: center; min-height: 40px; border: 1px solid #cbd5e1; background: white; color: #173557; padding: 8px 16px; border-radius: 9px; text-decoration: none; cursor: pointer; font-weight: 700; font-size: 13px; }
        .toolbar button { background: #fbbf24; color: #101f35; border-color: #e5a000; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 9px 7px; border: 1px solid #cbd5e1; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #edf2f8; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #334155; }
        tbody tr:nth-child(even) { background: #fafcff; }
        .meta { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 12px; }
        .signatures { display: flex; justify-content: space-between; gap: 35px; margin-top: 65px; }
        .signature { flex: 1; border-top: 1px solid #555; padding-top: 7px; text-align: center; }
        @page { size: A4 landscape; margin: 12mm; }
        @media print {
            body { margin: 0; font-size: 11px; }
            .toolbar { display: none; }
            th { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            tr { break-inside: avoid; }
        }
    </style>
    @if(request()->boolean('auto_print'))
    <script>
        window.addEventListener('load', () => {
            window.print();
        }, { once: true });
    </script>
    @endif
</head>
<body>
    @unless(request()->boolean('embedded'))
    <div class="toolbar">
        <a href="{{ route('driver-bus-assignment', ['trip_date' => $selectedTripDate], false) }}">Back to Assignments</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
    @endunless
    <header>
        <div>
            <h1>GCT-Systems</h1>
            <h2>Daily Driver &amp; Bus Assignment Sheet</h2>
            <div class="muted">Confirmed assignments from the Operations system</div>
        </div>
        <div><strong>Date:</strong> {{ \Carbon\Carbon::parse($selectedTripDate)->format('F d, Y') }}<br><strong>Assignments:</strong> {{ $assignments->count() + $externalAssignments->count() }}</div>
    </header>
    <div class="meta">
        <span>Only saved driver and bus assignments are included.</span>
        <span>Generated: {{ now(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y h:i A') }}</span>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:11%">Trip / Slot</th><th style="width:14%">Time / Shift</th>
                <th style="width:20%">Route</th><th style="width:20%">Driver</th>
                <th style="width:20%">Bus / Plate</th><th style="width:15%">Trip Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($assignments as $assignment)
                @php
                    $trip = $assignment->tripSchedule;
                    $slot = $assignment->slot_number ?? ($slotNumbers[$assignment->id] ?? 1);
                @endphp
                <tr>
                    <td><strong>{{ $trip->trip_code }}</strong>@if($trip->required_buses > 1)<div>Slot {{ $slot }} of {{ $trip->required_buses }}</div>@endif</td>
                    <td>{{ \Carbon\Carbon::parse($trip->departure_time)->format('g:i A') }}<div>{{ $trip->shift }}</div></td>
                    <td><strong>{{ $trip->shuttleRoute?->route_code ?? '—' }}</strong><div>{{ $trip->shuttleRoute?->route_name ?? '—' }}</div></td>
                    <td>{{ $assignment->driver_name }}</td>
                    <td>{{ $assignment->bus?->bus_no ?? '—' }}<div>{{ $assignment->bus?->plate_no ?? '—' }}</div>
                        @if($assignment->bus?->fleet_purpose === 'Reserve')
                            <div style="font-weight:700;color:#1e6b44;">
                                {{ $assignment->reserveApproval ? 'Reserve · OIC Approved' : 'Reserve · Approval Not Recorded' }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $trip->status }} / {{ $trip->assignment_status }}</td>
                </tr>
            @empty
                @if($externalAssignments->isEmpty())
                    <tr><td colspan="6" style="text-align:center;padding:28px;">No confirmed assignments for this date.</td></tr>
                @endif
            @endforelse
            @foreach($externalAssignments as $external)
                @php $trip = $external->tripSchedule; @endphp
                <tr>
                    <td><strong>{{ $trip->trip_code }}</strong><div>Slot {{ $external->slot_number }} of {{ $trip->required_buses }}</div></td>
                    <td>{{ \Carbon\Carbon::parse($trip->departure_time)->format('g:i A') }}<div>{{ $trip->shift }}</div></td>
                    <td><strong>{{ $trip->shuttleRoute?->route_code ?? '—' }}</strong><div>{{ $trip->shuttleRoute?->route_name ?? '—' }}</div></td>
                    <td>{{ $external->driver_name }}<div>External driver</div></td>
                    <td>{{ $external->plate_number }}<div>Rented · {{ $external->company_name }}</div></td>
                    <td>{{ $trip->status }} / {{ $trip->assignment_status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="signatures"><div class="signature">Prepared by / Operations Staff</div><div class="signature">Reviewed by / Operations Head</div></div>
</body>
</html>
