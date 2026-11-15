<x-layout.app
    title="FROMS - Client Trip Times"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Scheduling_And_Dispatch/client-trip-times.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Scheduling_And_Dispatch/client-trip-times.js',
    ]"
>
    @php
        $canEditOperation = $canEditOperation ?? (auth()->user()?->hasSystemPermission('operation', 'edit') ?? false);
    @endphp

    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main
            class="main client-trip-times-page"
            data-editable="{{ $canEditOperation ? 'true' : 'false' }}"
        >
            <x-layout.topbar
                title="Client Trip Times"
                subtitle="Configure Incoming and Outgoing AM and PM trip time slots for each client"
                notification-count="4"
            />

            @php
                $categoryCount = count($categoryLabels);
                $totalClientCount = $clients->count();
                $totalConfiguredCategories = $clients->sum(fn ($client) => $client->tripTimeConfigurations->count());
                $totalPossibleCategories = $totalClientCount * $categoryCount;
                $totalPendingCategories = max(0, $totalPossibleCategories - $totalConfiguredCategories);
                $totalFullyConfiguredClients = $clients->filter(fn ($client) => $client->tripTimeConfigurations->count() >= $categoryCount)->count();
                $clientsNeedingSetup = max(0, $totalClientCount - $totalFullyConfiguredClients);
            @endphp

            <section class="ctt-overview" aria-label="Trip time configuration overview">
                <article class="ctt-overview-card">
                    <span class="ctt-overview-icon ctt-overview-icon-blue"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                    <div class="ctt-overview-copy">
                        <span class="ctt-overview-label">Total Clients</span>
                        <strong class="ctt-overview-value" data-stat="clients">{{ $totalClientCount }}</strong>
                        <span class="ctt-overview-note">Client master records</span>
                    </div>
                </article>
                <article class="ctt-overview-card">
                    <span class="ctt-overview-icon ctt-overview-icon-green"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                    <div class="ctt-overview-copy">
                        <span class="ctt-overview-label">Fully Configured</span>
                        <strong class="ctt-overview-value" data-stat="complete-clients">{{ $totalFullyConfiguredClients }}</strong>
                        <span class="ctt-overview-note">All four categories set</span>
                    </div>
                </article>
                <article class="ctt-overview-card">
                    <span class="ctt-overview-icon ctt-overview-icon-amber"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
                    <div class="ctt-overview-copy">
                        <span class="ctt-overview-label">Needs Setup</span>
                        <strong class="ctt-overview-value" data-stat="needs-setup">{{ $clientsNeedingSetup }}</strong>
                        <span class="ctt-overview-note">Clients with missing categories</span>
                    </div>
                </article>
                <article class="ctt-overview-card">
                    <span class="ctt-overview-icon ctt-overview-icon-purple"><i class="fa-solid fa-clock" aria-hidden="true"></i></span>
                    <div class="ctt-overview-copy">
                        <span class="ctt-overview-label">Categories Configured</span>
                        <strong class="ctt-overview-value" data-stat="configured">
                            <span data-configured-value>{{ $totalConfiguredCategories }}</span><small> / {{ $totalPossibleCategories }}</small>
                        </strong>
                        <span class="ctt-overview-note">Four categories per client</span>
                    </div>
                </article>
            </section>

            @if(session('success'))
                <p role="status" class="ctt-client-create-message">{{ session('success') }}</p>
            @endif
            <section class="ctt-configurations-card" aria-label="Client trip time configurations">
                <div class="ctt-section-header">
                    <div>
                        <h2>Client Configurations</h2>
                        <p>Manage the allowed time slots for incoming and outgoing trips by client.</p>
                    </div>
                    @if($canEditOperation)
                        <button type="button" class="ctt-btn ctt-btn-primary ctt-create-client-primary" id="cttAddClientButton"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create Client</button>
                    @endif
                </div>

                <div class="ctt-toolbar" aria-label="Client search, filter and sorting tools">
                    <x-ui.table-toolbar
                        action="{{ route('client-trip-times') }}"
                        form-id="cttClientToolbar"
                        search-label="Search Clients"
                        search-placeholder="Search clients..."
                        filter-title="Filter Clients"
                        filter-help="Show clients by their trip-time configuration progress."
                        sort-title="Sort Clients"
                        :sort-options="['name-asc' => 'Client Name', 'most-configured' => 'Most Configured', 'least-configured' => 'Least Configured']"
                        default-sort="name-asc"
                        data-client-filter="true"
                    >
                        <label class="gct-table-filter-field" for="cttClientFilter">
                            <span>Configuration Status</span>
                            <select id="cttClientFilter" name="client_status" data-default="all">
                                <option value="all">All Clients</option>
                                <option value="needs-setup">Needs Setup</option>
                                <option value="complete">Fully Configured</option>
                            </select>
                        </label>
                    </x-ui.table-toolbar>

                    <div class="ctt-toolbar-actions">
                        @if($clients->isNotEmpty())
                            <button type="button" class="ctt-btn ctt-btn-ghost ctt-toggle-all" id="cttToggleAllClients">
                                <i class="fa-solid fa-angles-down" aria-hidden="true"></i>
                                <span>Expand all</span>
                            </button>
                        @endif
                        <span class="ctt-client-count" id="cttClientCount">
                            {{ $clients->count() }} {{ \Illuminate\Support\Str::plural('Client', $clients->count()) }}
                        </span>
                    </div>
                </div>

            <section class="ctt-clients" id="cttClients">
                @forelse($clients as $client)
                    @php
                        $configuredCategories = $client->tripTimeConfigurations->pluck('category')->all();
                        $configuredCategoryCount = count($configuredCategories);
                        $totalCategoryCount = count($categoryLabels);
                        $configurationPercent = $totalCategoryCount > 0 ? (int) round(($configuredCategoryCount / $totalCategoryCount) * 100) : 0;
                    @endphp
                    <details
                        class="ctt-client-card"
                        data-client-id="{{ $client->id }}"
                        data-client-name="{{ mb_strtolower($client->name) }}"
                        data-configured-count="{{ $configuredCategoryCount }}"
                        data-total-categories="{{ $totalCategoryCount }}"
                    >
                        <summary class="ctt-client-header">
                            <span class="ctt-client-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                            <div class="ctt-client-heading">
                                <h3>{{ $client->name }}
                                    <span class="ctt-client-status {{ $client->isActive() ? 'is-active' : 'is-inactive' }}">
                                        <i class="fa-solid {{ $client->isActive() ? 'fa-circle-check' : 'fa-circle-minus' }}" aria-hidden="true"></i>
                                        {{ $client->status }}
                                    </span>
                                </h3>
                                <span>Client master record</span>
                            </div>

                            <span class="ctt-client-category-indicators" aria-label="Category configuration status">
                                @foreach($categoryLabels as $category => $categoryLabel)
                                    @php
                                        $shortCategoryLabel = match ($category) {
                                            'incoming_am' => 'IN AM',
                                            'incoming_pm' => 'IN PM',
                                            'outgoing_am' => 'OUT AM',
                                            'outgoing_pm' => 'OUT PM',
                                            default => $categoryLabel,
                                        };
                                        $isCategoryConfigured = in_array($category, $configuredCategories, true);
                                    @endphp
                                    <span
                                        class="ctt-category-indicator {{ $isCategoryConfigured ? 'is-configured' : '' }}"
                                        data-category-chip="{{ $category }}"
                                        title="{{ $categoryLabel }}: {{ $isCategoryConfigured ? 'Configured' : 'Not configured' }}"
                                    >
                                        <i class="fa-solid {{ $isCategoryConfigured ? 'fa-circle-check' : 'fa-circle' }}" aria-hidden="true"></i>
                                        <span>{{ $shortCategoryLabel }}</span>
                                    </span>
                                @endforeach
                            </span>

                            <span class="ctt-client-coverage">
                                <span class="ctt-client-config-count {{ $configuredCategoryCount === $totalCategoryCount ? 'is-complete' : '' }}">
                                    <strong><span data-configured-count>{{ $configuredCategoryCount }}</span><small>/{{ $totalCategoryCount }}</small></strong>
                                    <span>configured</span>
                                </span>
                                <span class="ctt-progress-track" aria-label="{{ $configurationPercent }} percent configured">
                                    <span class="ctt-progress-fill" style="width: {{ $configurationPercent }}%"></span>
                                </span>
                            </span>

                            <span class="ctt-client-expand" aria-hidden="true">
                                <span>{{ $configuredCategoryCount > 0 ? 'Manage settings' : 'Configure client' }}</span>
                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            </span>
                        </summary>

                        <section class="ctt-client-buses" aria-label="Allocated buses for {{ $client->name }}">
                            <div class="ctt-client-buses-heading">
                                <div>
                                    <strong><i class="fa-solid fa-bus-simple" aria-hidden="true"></i> Allocated Buses</strong>
                                    <span>{{ $client->buses->count() }} {{ \Illuminate\Support\Str::plural('bus', $client->buses->count()) }}</span>
                                </div>
                                <a href="{{ route('bus-master-list') }}" class="ctt-client-buses-link">
                                    Manage in Bus Master List <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                </a>
                            </div>
                            <div class="ctt-client-bus-chips">
                                @forelse($client->buses as $bus)
                                    <span class="ctt-client-bus-chip"><i class="fa-solid fa-bus" aria-hidden="true"></i> Bus {{ $bus->bus_no }}</span>
                                @empty
                                    <span class="ctt-client-no-buses">No buses allocated to this client.</span>
                                @endforelse
                            </div>
                        </section>

                        <div class="ctt-category-grid" role="group" aria-label="Trip time categories for {{ $client->name }}">
                            @foreach($categoryLabels as $category => $categoryLabel)
                                @php
                                    $configuration = $client->tripTimeConfigurations
                                        ->first(fn ($item) => $item->category === $category);
                                @endphp
                                <div
                                    class="ctt-category-panel"
                                    id="ctt-panel-{{ $client->id }}-{{ $category }}"
                                    data-client-id="{{ $client->id }}"
                                    data-category="{{ $category }}"
                                >
                                    @include('Operation.Scheduling_And_Dispatch.partials.client-trip-time-panel', [
                                        'client' => $client,
                                        'category' => $category,
                                        'categoryLabel' => $categoryLabel,
                                        'configuration' => $configuration,
                                        'canEditOperation' => $canEditOperation && $client->isActive(),
                                    ])
                                </div>
                            @endforeach
                        </div>
                        @if($canEditOperation)
                            <div class="ctt-client-status-actions">
                                <span>Client availability for new trips and allocations</span>
                                <form method="POST" action="{{ route('operation.clients.status', $client) }}"
                                    data-confirm-form
                                    data-confirm-type="status"
                                    data-confirm-title="{{ $client->isActive() ? 'Deactivate Client?' : 'Reactivate Client?' }}"
                                    data-confirm-message="{{ $client->isActive() ? 'Deactivate '.$client->name.'? Existing history will be retained. Clients with future or ongoing trips cannot be deactivated.' : 'Reactivate '.$client->name.'? This client will be available for new trip schedules and bus allocations.' }}"
                                    data-confirm-button="{{ $client->isActive() ? 'Yes, Deactivate' : 'Yes, Reactivate' }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $client->isActive() ? 'Inactive' : 'Active' }}">
                                    <button type="submit" class="ctt-btn ctt-btn-ghost">
                                        {{ $client->isActive() ? 'Deactivate Client' : 'Reactivate Client' }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    </details>
                @empty
                    <div class="ctt-page-empty" id="cttPageEmpty">
                        <i class="fa-solid fa-building-circle-xmark" aria-hidden="true"></i>
                        <strong>No clients available</strong>
                        <span>Create a client using the Create Client button above to begin configuring departure times.</span>
                    </div>
                @endforelse

                <div class="ctt-search-empty" id="cttSearchEmpty" hidden>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <strong>No matching clients</strong>
                    <span>Try a different name or change the filter.</span>
                </div>
            </section>
            </section>

            <x-ui.form-modal
                id="cttConfigModal"
                title="Client Trip Time Range"
                title-id="cttConfigTitle"
                description="Set the time window for this client category."
                icon="fa-clock"
                size="medium"
                form-id="cttConfigForm"
                action="#"
                method="POST"
                submit-text="Save Configuration"
                submit-text-id="cttConfigSubmitText"
                cancel-text="Cancel"
                cancel-id="cttConfigCancel"
                close-id="cttConfigClose"
            >
                <div class="ctt-modal-context">
                    <span class="ctt-modal-context-item">
                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                        <strong id="cttConfigClientName">â€”</strong>
                    </span>
                    <span class="ctt-modal-context-item">
                        <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                        <strong id="cttConfigCategoryLabel">â€”</strong>
                    </span>
                </div>

                <input type="hidden" name="operation_client_id" id="cttConfigClientId">
                <input type="hidden" name="category" id="cttConfigCategory">

                <div class="ui-form-grid ctt-modal-grid">
                    <div class="ui-form-group">
                        <label for="cttConfigStart">Start Time <span class="ui-required">*</span></label>
                        <div class="ui-input-wrap has-icon">
                            <span class="ui-input-icon"><i class="fa-solid fa-sun" aria-hidden="true"></i></span>
                            <input type="time" name="start_time" id="cttConfigStart" required>
                        </div>
                        <span class="ctt-field-error" data-error-for="start_time"></span>
                    </div>

                    <div class="ui-form-group">
                        <label for="cttConfigEnd">End Time <span class="ui-required">*</span></label>
                        <div class="ui-input-wrap has-icon">
                            <span class="ui-input-icon"><i class="fa-solid fa-moon" aria-hidden="true"></i></span>
                            <input type="time" name="end_time" id="cttConfigEnd" required>
                        </div>
                        <span class="ctt-field-error" data-error-for="end_time"></span>
                    </div>

                    <div class="ui-form-group ui-form-full">
                        <label class="ctt-checkbox-row" for="cttConfigActive">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" id="cttConfigActive" value="1" checked>
                            <span>
                                <strong>Active configuration</strong>
                                <small>When disabled, new trips for this category are blocked until it is enabled again.</small>
                            </span>
                        </label>
                        <span class="ctt-field-error" data-error-for="is_active"></span>
                    </div>
                </div>

                <p class="ctt-modal-help">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    AM categories run from 12:00 AM up to 12:00 NN. PM categories run from 12:00 NN onward.
                    Ranges cannot cross midnight. Time slots are added per category after the range is saved.
                </p>
            </x-ui.form-modal>

            <x-ui.form-modal
                id="cttSlotModal"
                title="Edit Time Slot"
                title-id="cttSlotTitle"
                description="Change the time or toggle whether this slot can be selected."
                icon="fa-clock"
                size="small"
                form-id="cttSlotForm"
                action="#"
                method="POST"
                submit-text="Save Slot"
                cancel-text="Cancel"
                cancel-id="cttSlotCancel"
                close-id="cttSlotClose"
            >
                <input type="hidden" name="slot_id" id="cttSlotId">

                <div class="ui-form-group">
                    <label for="cttSlotTime">Slot Time <span class="ui-required">*</span></label>
                    <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></span>
                        <input type="time" name="slot_time" id="cttSlotTime" required>
                    </div>
                    <span class="ctt-field-error" data-error-for="slot_time"></span>
                </div>

                <div class="ui-form-group">
                    <label class="ctt-checkbox-row" for="cttSlotEnabled">
                        <input type="hidden" name="is_enabled" value="0">
                        <input type="checkbox" name="is_enabled" id="cttSlotEnabled" value="1" checked>
                        <span>
                            <strong>Slot enabled</strong>
                            <small>Disabled slots stay saved but cannot be selected for new trips.</small>
                        </span>
                    </label>
                </div>

                <div class="ctt-slot-remove-wrap">
                    <button type="button" class="ctt-btn ctt-btn-danger" id="cttSlotRemove">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i> Remove Slot
                    </button>
                </div>
            </x-ui.form-modal>
            @if($canEditOperation)
                <x-ui.form-modal
                    id="cttCreateClientModal"
                    title="Create Client"
                    description="Add a client record, then configure its allowed departure times."
                    icon="fa-building"
                    size="small"
                    form-id="cttCreateClientForm"
                    :action="route('operation.clients.store')"
                    submit-text="Save Client"
                >
                    <div class="ui-form-group">
                        <label for="cttNewClientName">Client Name <span class="ui-required">*</span></label>
                        <input type="text" id="cttNewClientName" name="name" minlength="2" maxlength="150" value="{{ old('name') }}" required autocomplete="off" placeholder="Enter client name">
                        @error('name')
                            <span class="ctt-field-error" role="alert">{{ $message }}</span>
                        @enderror
                    </div>
                </x-ui.form-modal>
            @endif

        </main>
    </div>
</x-layout.app>
