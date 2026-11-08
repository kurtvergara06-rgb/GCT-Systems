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
                        <button class="client-master-add" type="button" data-client-create-open @if($errors->any() && !old('editing_client_id')) data-open-on-error="true" @endif>
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

                <div class="client-master-record-controls">
                    <nav class="client-master-record-tabs" aria-label="Client record groups">
                        <a class="client-master-record-tab {{ $selectedTab === 'active' ? 'is-active' : '' }}"
                           href="{{ route('operation.clients.index') }}?{{ http_build_query(array_merge(request()->except(['tab', 'status', 'page']), ['tab' => 'active'])) }}">
                            <i class="fa-solid fa-building-circle-check" aria-hidden="true"></i> Active Clients
                        </a>
                        <a class="client-master-record-tab {{ $selectedTab === 'history' ? 'is-active' : '' }}"
                           href="{{ route('operation.clients.index') }}?{{ http_build_query(array_merge(request()->except(['tab', 'status', 'page']), ['tab' => 'history'])) }}">
                            <i class="fa-solid fa-building-circle-xmark" aria-hidden="true"></i> Inactive Clients
                        </a>
                    </nav>
                    <x-ui.table-toolbar
                        :action="route('operation.clients.index')"
                        form-id="clientMasterToolbar"
                        search-label="Search Clients"
                        search-placeholder="Search company name..."
                        filter-title="Filter Clients"
                        filter-help="Filter by trip-time configuration progress."
                        :filter-count="$selectedSetup !== '' ? 1 : 0"
                        :sort-options="['name' => 'Client Name', 'created_at' => 'Date Registered']"
                        default-sort="name"
                    >
                        <x-slot:hidden>
                            <input type="hidden" name="tab" value="{{ $selectedTab }}">
                        </x-slot:hidden>
                        <x-slot:filters>
                            <x-ui.table-toolbar-field label="Trip-Time Setup" name="setup">
                                <option value="" @selected($selectedSetup === '')>All Clients</option>
                                <option value="complete" @selected($selectedSetup === 'complete')>Fully Configured</option>
                                <option value="needs-setup" @selected($selectedSetup === 'needs-setup')>Needs Setup</option>
                            </x-ui.table-toolbar-field>
                        </x-slot:filters>
                    </x-ui.table-toolbar>
                </div>
                <div class="client-master-filter-loading" data-server-filter-loading hidden>
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    Updating client records...
                </div>

                <div class="client-master-table-wrap gct-responsive-records gct-sticky-actions gct-master-scroll-10" role="region" tabindex="0" aria-label="Client records table, horizontally scrollable">
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
                                            <span class="client-master-name-text">
                                                <strong title="{{ $client->name }}">{{ $client->name }}</strong>
                                                <small>{{ $client->contact_person ?: 'No contact person yet' }}@if($client->contact_number) · {{ $client->contact_number }}@endif</small>
                                            </span>
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
                                            <button type="button" class="client-master-action" data-client-detail-open data-client-mode="view"
                                                data-client-id="CL-{{ str_pad((string) $client->id, 4, '0', STR_PAD_LEFT) }}"
                                                data-client-name="{{ $client->name }}"
                                                data-client-address="{{ $client->company_address }}"
                                                data-client-contact-person="{{ $client->contact_person }}"
                                                data-client-contact-position="{{ $client->contact_position }}"
                                                data-client-contact-number="{{ $client->contact_number }}"
                                                data-client-contact-email="{{ $client->contact_email }}"
                                                data-client-status="{{ $client->status }}"
                                                data-client-buses="{{ $client->buses_count }}"
                                                data-client-trip-times="{{ $client->trip_time_configurations_count }}"
                                                title="View {{ $client->name }}" aria-label="View {{ $client->name }}">
                                                <i class="fa-solid fa-eye" aria-hidden="true"></i><span>View</span>
                                            </button>
                                            @if($canEditOperation)
                                                <button type="button" class="client-master-action" data-client-detail-open data-client-mode="edit" data-client-record-id="{{ $client->id }}"
                                                    data-client-id="CL-{{ str_pad((string) $client->id, 4, '0', STR_PAD_LEFT) }}"
                                                    data-client-name="{{ $client->name }}"
                                                    data-client-address="{{ $client->company_address }}"
                                                    data-client-contact-person="{{ $client->contact_person }}"
                                                    data-client-contact-position="{{ $client->contact_position }}"
                                                    data-client-contact-number="{{ $client->contact_number }}"
                                                    data-client-contact-email="{{ $client->contact_email }}"
                                                    data-client-status="{{ $client->status }}"
                                                    data-client-buses="{{ $client->buses_count }}"
                                                    data-client-trip-times="{{ $client->trip_time_configurations_count }}"
                                                    data-client-update-url="{{ route('operation.clients.update', $client) }}"
                                                    title="Edit {{ $client->name }}" aria-label="Edit {{ $client->name }}">
                                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>Edit</span>
                                                </button>
                                            @endif
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
                                    <span>{{ $search !== '' || $selectedSetup !== '' ? 'Try changing your search or filter.' : 'Create a client to begin managing shuttle service requirements.' }}</span>
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

            <x-ui.form-modal id="clientMasterDetailModal" title="Client Details" title-id="clientMasterDetailTitle"
                data-edit-error-client="{{ old('editing_client_id') }}"
                @if(old('editing_client_id'))
                    data-old-client-name="{{ old('name') }}"
                    data-old-client-address="{{ old('company_address') }}"
                    data-old-client-contact-person="{{ old('contact_person') }}"
                    data-old-client-contact-position="{{ old('contact_position') }}"
                    data-old-client-contact-number="{{ old('contact_number') }}"
                    data-old-client-contact-email="{{ old('contact_email') }}"
                @endif
                icon="fa-building" description="Client profile details; trip times and bus allocations are managed separately."
                size="wide" form-id="clientMasterDetailForm" action="#" method="PUT"
                submit-text="Update Client" submit-id="clientMasterDetailSubmit" cancel-text="Close">
                <input type="hidden" name="editing_client_id" id="clientDetailEditingId">
                <div class="client-master-detail-summary" aria-label="Client reference and allocation overview">
                    <div><small>Client ID (permanent)</small><strong id="clientDetailId">—</strong></div>
                    <div><small>Status</small><strong id="clientDetailStatus">—</strong></div>
                    <div><small>Allocated Buses</small><strong id="clientDetailBuses">—</strong></div>
                    <div><small>Trip-Time Setup</small><strong id="clientDetailTripTimes">—</strong></div>
                </div>
                <div class="client-master-form-section">
                    <h3><span>01</span> Company Information</h3>
                    <div class="client-master-form-grid">
                        <div class="ui-form-group client-master-form-wide">
                            <label for="clientDetailName">Company / Client Name <span class="ui-required">*</span></label>
                            <input id="clientDetailName" name="name" type="text" required minlength="2" maxlength="150" autocomplete="organization">
                        </div>
                        <div class="ui-form-group client-master-form-wide">
                            <label for="clientDetailAddress">Company Address</label>
                            <textarea id="clientDetailAddress" name="company_address" rows="2" maxlength="500"></textarea>
                        </div>
                    </div>
                </div>
                <div class="client-master-form-section">
                    <h3><span>02</span> Primary Contact</h3>
                    <div class="client-master-form-grid">
                        <div class="ui-form-group">
                            <label for="clientDetailPerson">Contact Person</label>
                            <input id="clientDetailPerson" name="contact_person" type="text" maxlength="150" autocomplete="name">
                        </div>
                        <div class="ui-form-group">
                            <label for="clientDetailPosition">Department / Position</label>
                            <input id="clientDetailPosition" name="contact_position" type="text" maxlength="150">
                        </div>
                        <div class="ui-form-group">
                            <label for="clientDetailPhone">Contact Number</label>
                            <input id="clientDetailPhone" name="contact_number" type="tel" maxlength="30" autocomplete="tel">
                        </div>
                        <div class="ui-form-group">
                            <label for="clientDetailEmail">Email Address</label>
                            <input id="clientDetailEmail" name="contact_email" type="email" maxlength="254" autocomplete="email">
                        </div>
                    </div>
                    <p class="client-master-form-note">Client ID cannot be changed. Bus allocations and trip-time configurations remain in their respective pages.</p>
                </div>
            </x-ui.form-modal>

            @if($canEditOperation)
                <x-ui.form-modal
                    id="clientMasterCreateModal" title="Add Client" icon="fa-building"
                    description="Register a shuttle service client. Trip times can be configured separately."
                    size="wide" form-id="clientMasterCreateForm"
                    :action="route('operation.clients.store')" submit-text="Save Client"
                >
                    <input type="hidden" name="return_to" value="client-master-list">
                    <div class="client-master-form-section">
                        <h3><span>01</span> Company Information</h3>
                        <div class="client-master-form-grid">
                            <div class="ui-form-group client-master-form-wide">
                                <label for="clientMasterName">Company / Client Name <span class="ui-required">*</span></label>
                                <input id="clientMasterName" type="text" name="name" minlength="2" maxlength="150" required autocomplete="organization" value="{{ old('name') }}" placeholder="Enter registered company name">
                                @error('name')<small class="client-master-field-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="ui-form-group client-master-form-wide">
                                <label for="clientMasterAddress">Company Address</label>
                                <textarea id="clientMasterAddress" name="company_address" rows="2" maxlength="500" placeholder="Enter main business address">{{ old('company_address') }}</textarea>
                                @error('company_address')<small class="client-master-field-error">{{ $message }}</small>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="client-master-form-section">
                        <h3><span>02</span> Primary Contact</h3>
                        <div class="client-master-form-grid">
                            <div class="ui-form-group">
                                <label for="clientMasterContactPerson">Contact Person <span class="ui-required">*</span></label>
                                <input id="clientMasterContactPerson" type="text" name="contact_person" maxlength="150" required autocomplete="name" value="{{ old('contact_person') }}" placeholder="Full name">
                                @error('contact_person')<small class="client-master-field-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="ui-form-group">
                                <label for="clientMasterPosition">Department / Position</label>
                                <input id="clientMasterPosition" type="text" name="contact_position" maxlength="150" value="{{ old('contact_position') }}" placeholder="e.g., HR Officer">
                                @error('contact_position')<small class="client-master-field-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="ui-form-group">
                                <label for="clientMasterPhone">Contact Number <span class="ui-required">*</span></label>
                                <input id="clientMasterPhone" type="tel" name="contact_number" maxlength="30" required autocomplete="tel" value="{{ old('contact_number') }}" placeholder="e.g., 09123456789">
                                @error('contact_number')<small class="client-master-field-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="ui-form-group">
                                <label for="clientMasterEmail">Email Address</label>
                                <input id="clientMasterEmail" type="email" name="contact_email" maxlength="254" autocomplete="email" value="{{ old('contact_email') }}" placeholder="contact@company.com">
                                @error('contact_email')<small class="client-master-field-error">{{ $message }}</small>@enderror
                            </div>
                        </div>
                    </div>
                </x-ui.form-modal>
            @endif
        </main>
    </div>
</x-layout.app>
