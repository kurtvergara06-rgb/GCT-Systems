<x-layout.app
    title="FROMS - Client Master List"
    :assets="[
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Clients/client-master-list.css',
        'resources/js/Operation/Clients/client-master-list.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Operation" />
        <main class="main client-master-page">
            <x-layout.topbar title="Client Master List" subtitle="Manage shuttle service clients and their transportation configurations" notification-count="0" />

            <section class="client-master-stats" aria-label="Client overview">
                <x-ui.summary-card label="Total Clients" :value="$totalClients" small="Registered client companies" icon="fa-building" color="blue" />
                <x-ui.summary-card label="Active Clients" :value="$activeClients" small="Eligible for new schedules" icon="fa-circle-check" color="green" />
                <x-ui.summary-card label="Inactive Clients" :value="$totalClients - $activeClients" small="Not accepting new schedules" icon="fa-circle-pause" color="red" />
                <x-ui.summary-card label="Fully Configured" :value="$configuredClients" small="All four trip-time categories" icon="fa-clock" color="yellow" />
            </section>

            <section class="client-master-panel" data-ajax-region="records">
                <div class="client-master-header">
                    <div>
                        <h2>Registered Clients</h2>
                        <p>Maintain client records here. Trip-time configurations remain in Trip Planning &amp; Scheduling.</p>
                    </div>
                    @if($canEditOperation)
                        <button class="client-master-add" type="button" data-client-create-open @if($errors->has('name')) data-open-on-error="true" @endif>
                            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Client
                        </button>
                    @endif
                </div>

                @if(session('success'))
                    <div class="client-master-notice" role="status">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="client-master-error" role="alert">{{ $errors->first() }}</div>
                @endif

                <form action="{{ route('operation.clients.index') }}" method="GET" class="client-master-filters" aria-label="Search and filter clients">
                    <label class="client-master-search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <span class="sr-only">Search client names</span>
                        <input type="search" name="search" placeholder="Search client name..." value="{{ $search }}" maxlength="150">
                    </label>
                    <label>
                        <span class="sr-only">Client status</span>
                        <select name="status" aria-label="Filter by status">
                            <option value="all" @selected($selectedStatus === 'all')>All Statuses</option>
                            <option value="Active" @selected($selectedStatus === 'Active')>Active</option>
                            <option value="Inactive" @selected($selectedStatus === 'Inactive')>Inactive</option>
                        </select>
                    </label>
                    <label>
                        <span class="sr-only">Sort clients</span>
                        <select name="sort" aria-label="Sort clients">
                            <option value="name" @selected($selectedSort === 'name')>Client Name</option>
                            <option value="newest" @selected($selectedSort === 'newest')>Newest First</option>
                            <option value="oldest" @selected($selectedSort === 'oldest')>Oldest First</option>
                        </select>
                    </label>
                    <button type="submit" class="client-master-filter-btn">Apply</button>
                    <a class="client-master-clear" href="{{ route('operation.clients.index') }}">Reset</a>
                </form>

                <div class="client-master-table-wrap gct-responsive-records gct-sticky-actions" role="region" tabindex="0" aria-label="Client records table, horizontally scrollable">
                    <table class="client-master-table">
                        <thead>
                            <tr>
                                <th scope="col">Client ID</th>
                                <th scope="col">Client / Company</th>
                                <th scope="col">Allocated Buses</th>
                                <th scope="col">Trip-Time Setup</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clients as $client)
                                <tr>
                                    <td><span class="client-master-id">CL-{{ str_pad((string) $client->id, 4, '0', STR_PAD_LEFT) }}</span></td>
                                    <td>
                                        <span class="client-master-name">
                                            <span class="client-master-avatar"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                                            <span class="client-master-name-text"><strong title="{{ $client->name }}">{{ $client->name }}</strong><small>Shuttle service client</small></span>
                                        </span>
                                    </td>
                                    <td><span class="client-master-count">{{ $client->buses_count }} {{ \Illuminate\Support\Str::plural('bus', $client->buses_count) }}</span></td>
                                    <td>
                                        <span class="client-master-coverage {{ $client->trip_time_configurations_count >= 4 ? 'is-complete' : '' }}">
                                            {{ min(4, $client->trip_time_configurations_count) }}/4 configured
                                        </span>
                                    </td>
                                    <td><span class="client-master-status {{ $client->isActive() ? 'is-active' : 'is-inactive' }}">{{ $client->status }}</span></td>
                                    <td class="client-master-actions">
                                        <div class="client-master-action-row">
                                            <a class="client-master-action" href="{{ route('client-trip-times') }}?search={{ urlencode($client->name) }}" title="View trip times for {{ $client->name }}" aria-label="View trip times for {{ $client->name }}"><i class="fa-solid fa-clock" aria-hidden="true"></i><span>Trip Times</span></a>
                                            @if($canEditOperation)
                                                <form action="{{ route('operation.clients.status', $client) }}" method="POST"
                                                    data-confirm-form data-confirm-type="status"
                                                    data-confirm-title="{{ $client->isActive() ? 'Deactivate Client?' : 'Reactivate Client?' }}"
                                                    data-confirm-message="{{ $client->isActive() ? 'Deactivate '.$client->name.'? Future and ongoing schedules must be resolved first. History is retained.' : 'Reactivate '.$client->name.' for new schedules and allocations?' }}"
                                                    data-confirm-button="{{ $client->isActive() ? 'Yes, Deactivate' : 'Yes, Reactivate' }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $client->isActive() ? 'Inactive' : 'Active' }}">
                                                    <input type="hidden" name="return_to" value="client-master-list">
                                                    <button type="submit" class="client-master-action {{ $client->isActive() ? 'is-disable' : 'is-enable' }}">{{ $client->isActive() ? 'Deactivate' : 'Reactivate' }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="client-master-empty">
                                    <i class="fa-solid fa-building-circle-xmark" aria-hidden="true"></i>
                                    <strong>No clients found</strong>
                                    <span>{{ $search !== '' || $selectedStatus !== 'all' ? 'Try changing your search or filter.' : 'Create a client to begin managing shuttle service requirements.' }}</span>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <footer class="client-master-footer">
                    <span>Showing {{ $clients->firstItem() ?? 0 }} to {{ $clients->lastItem() ?? 0 }} of {{ $clients->total() }} clients</span>
                    @if($clients->hasPages())
                        <nav class="client-master-pagination" aria-label="Client pages">
                            @if($clients->onFirstPage())
                                <span aria-disabled="true">Previous</span>
                            @else
                                <a href="{{ $clients->previousPageUrl() }}">Previous</a>
                            @endif
                            <span>Page {{ $clients->currentPage() }} of {{ $clients->lastPage() }}</span>
                            @if($clients->hasMorePages())
                                <a href="{{ $clients->nextPageUrl() }}">Next</a>
                            @else
                                <span aria-disabled="true">Next</span>
                            @endif
                        </nav>
                    @endif
                </footer>
            </section>

            @if($canEditOperation)
                <x-ui.form-modal
                    id="clientMasterCreateModal" title="Add Client" icon="fa-building"
                    description="Register a shuttle service client. Trip times can be configured separately."
                    size="small" form-id="clientMasterCreateForm"
                    :action="route('operation.clients.store')" submit-text="Save Client"
                >
                    <input type="hidden" name="return_to" value="client-master-list">
                    <div class="ui-form-group">
                        <label for="clientMasterName">Client / Company Name <span class="ui-required">*</span></label>
                        <input id="clientMasterName" type="text" name="name" minlength="2" maxlength="150" required autocomplete="off" value="{{ old('name') }}" placeholder="Enter company name">
                        @error('name')<small class="client-master-field-error">{{ $message }}</small>@enderror
                    </div>
                </x-ui.form-modal>
            @endif
        </main>
    </div>
</x-layout.app>
