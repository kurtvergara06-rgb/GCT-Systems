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
            @endphp

            <section class="ctt-intro-card ctt-intro-card-compact">
                <span class="ctt-intro-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></span>
                <div class="ctt-intro-copy">
                    <span class="ctt-eyebrow">SCHEDULING RULES</span>
                    <p>Set allowed departure windows for each client. Saved trips are not changed when these rules are updated.</p>
                </div>
                <a class="ctt-btn ctt-btn-ghost" href="{{ route('auto-scheduling') }}">
                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Advanced Scheduling
                </a>
            </section>

            <section class="ctt-overview" aria-label="Trip time configuration overview">
                <article class="ctt-overview-card">
                    <span class="ctt-overview-icon ctt-overview-icon-blue"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                    <div class="ctt-overview-copy">
                        <span class="ctt-overview-label">Clients</span>
                        <strong class="ctt-overview-value" data-stat="clients">{{ $totalClientCount }}</strong>
                        <span class="ctt-overview-note">Client master records</span>
                    </div>
                </article>
                <article class="ctt-overview-card">
                    <span class="ctt-overview-icon ctt-overview-icon-green"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                    <div class="ctt-overview-copy">
                        <span class="ctt-overview-label">Categories configured</span>
                        <strong class="ctt-overview-value" data-stat="configured">{{ $totalConfiguredCategories }}<small> / {{ $totalPossibleCategories }}</small></strong>
                        <span class="ctt-overview-note">Across all clients</span>
                    </div>
                </article>
                <article class="ctt-overview-card">
                    <span class="ctt-overview-icon ctt-overview-icon-amber"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
                    <div class="ctt-overview-copy">
                        <span class="ctt-overview-label">Need configuration</span>
                        <strong class="ctt-overview-value" data-stat="pending">{{ $totalPendingCategories }}</strong>
                        <span class="ctt-overview-note">Categories still to set up</span>
                    </div>
                </article>
            </section>

            <section class="ctt-directory-heading" aria-labelledby="cttDirectoryTitle">
                <div class="ctt-directory-heading-icon">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 id="cttDirectoryTitle">Client configurations</h2>
                    <p>Manage the allowed departure windows and time slots for each client.</p>
                </div>
                <span class="ctt-directory-meta">
                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                    {{ $categoryCount }} categories per client
                </span>
            </section>

            <section class="ctt-toolbar">
                <div class="ctt-search-box">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input
                        type="search"
                        id="cttClientSearch"
                        placeholder="Search clients..."
                        aria-label="Search clients"
                    >
                </div>
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
            </section>

            <section class="ctt-clients" id="cttClients">
                @forelse($clients as $client)
                    @php
                        $configuredCategories = $client->tripTimeConfigurations->pluck('category')->all();
                        $configuredCategoryCount = count($configuredCategories);
                        $totalCategoryCount = count($categoryLabels);
                        $configurationPercent = $totalCategoryCount > 0 ? (int) round(($configuredCategoryCount / $totalCategoryCount) * 100) : 0;
                    @endphp
                    <details class="ctt-client-card" data-client-id="{{ $client->id }}" data-client-name="{{ mb_strtolower($client->name) }}">
                        <summary class="ctt-client-header">
                            <span class="ctt-client-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                            <div class="ctt-client-heading">
                                <h3>{{ $client->name }}</h3>
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

                            <span class="ctt-client-expand">
                                <span>{{ $configuredCategoryCount > 0 ? 'Manage settings' : 'Configure client' }}</span>
                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            </span>
                        </summary>

                        <div class="ctt-category-grid">
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
                                        'canEditOperation' => $canEditOperation,
                                    ])
                                </div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="ctt-page-empty" id="cttPageEmpty">
                        <i class="fa-solid fa-building-circle-xmark" aria-hidden="true"></i>
                        <strong>No clients available</strong>
                        <span>Add Client Master records from the Bus Master List client allocation editor before configuring trip times.</span>
                    </div>
                @endforelse

                <div class="ctt-search-empty" id="cttSearchEmpty" hidden>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <strong>No matching clients</strong>
                    <span>Try a different client name.</span>
                </div>
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
                                <small>When disabled, trips for this category are not restricted by these times.</small>
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
        </main>
    </div>
</x-layout.app>
