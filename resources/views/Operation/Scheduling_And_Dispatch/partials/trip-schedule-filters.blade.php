<x-ui.table-toolbar-field label="Trip Date" name="trip_date" type="date" :value="$selectedTripDate" />
<x-ui.table-toolbar-field label="Direction" name="direction" default="all">
    <option value="all">All Directions</option>
    <option value="Incoming" @selected(request('direction') === 'Incoming')>Incoming</option>
    <option value="Outgoing" @selected(request('direction') === 'Outgoing')>Outgoing</option>
</x-ui.table-toolbar-field>
<x-ui.table-toolbar-field label="Route" name="route" default="all">
    <option value="all">All Routes</option>
    @foreach($activeRoutes as $route)
        <option value="{{ $route->id }}" @selected(request('route') == $route->id)>{{ $route->route_name }}</option>
    @endforeach
</x-ui.table-toolbar-field>
<x-ui.table-toolbar-field label="Status" name="status" default="all">
    <option value="all">All Statuses</option>
    @foreach(request('tab') === 'history' ? ['Completed', 'Cancelled', 'Missed'] : ['Scheduled', 'Ready', 'Dispatched'] as $status)
        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
    @endforeach
</x-ui.table-toolbar-field>
