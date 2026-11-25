<div class="ma-table-wrap {{ $recommendations ? 'ma-recommendations-wrap' : 'ma-findings-wrap' }}"><table class="ma-table {{ $recommendations ? 'ma-recommendations-table' : 'ma-findings-table' }}">
    <thead><tr><th>{{ $recommendations ? 'Priority' : 'Bus ID' }}</th><th>{{ $recommendations ? 'Bus ID' : 'Finding' }}</th><th>{{ $recommendations ? 'Finding' : 'Severity' }}</th>@if($recommendations)<th>Suggested Action</th>@endif<th>View Details</th></tr></thead>
    <tbody>@forelse($rows as $row)
        <tr>
            @if($recommendations)<td class="ma-severity-cell"><span class="ma-badge" data-level="{{ $row['priority'] }}" title="Rule-based severity based on recorded Maintenance evidence">{{ $row['priority'] }}</span></td><th scope="row">{{ $row['bus'] }}</th>
            @else<th scope="row">{{ $row['bus'] }}</th>@endif
            <td class="ma-finding-description"><strong class="ma-finding-type">{{ $row['title'] }}</strong><p>{{ $row['evidence'] }}</p></td>
            @if($recommendations)<td class="ma-suggested-action">{{ $row['action'] }}</td>@else<td><span class="ma-badge" data-level="{{ $row['priority'] }}">{{ $row['priority'] }}</span></td>@endif
            <td class="ma-finding-action"><button type="button" class="ma-button ma-detail-open" data-dialog="maFinding{{ $loop->index }}"><i class="fa-regular fa-eye" aria-hidden="true"></i> View Details</button></td>
        </tr>
    @empty<tr><td colspan="{{ $recommendations ? 5 : 4 }}" class="ma-empty">No verified findings match these filters. This does not establish that every vehicle is fault-free.</td></tr>@endforelse</tbody>
</table></div>
@foreach($rows as $row)
    <x-ui.dialog native class="ma-dialog" :id="'maFinding'.$loop->index" :labelled-by="'maFindingTitle'.$loop->index">
        <header><h2 id="maFindingTitle{{ $loop->index }}">{{ $row['title'] }}</h2><button type="button" class="ma-button ma-detail-close" aria-label="Close details">×</button></header>
        <dl><dt>Bus</dt><dd>{{ $row['bus'] }}</dd><dt>Recorded reference</dt><dd>{{ $row['reference'] }}</dd><dt>Evidence</dt><dd>{{ $row['evidence'] }}</dd><dt>Review action</dt><dd>{{ $row['action'] }}</dd></dl>
        <p class="ma-notice">Read-only analytics. Use the existing authorized Maintenance workflow to review or change a record.</p>
    </x-ui.dialog>
@endforeach
