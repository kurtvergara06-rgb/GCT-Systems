<?php

use Illuminate\Support\Facades\Blade;

it('renders the reusable search filter and sort controls with preserved query values', function (): void {
    request()->merge([
        'search' => 'GCT-101',
        'status' => 'Active',
        'sort' => 'name',
        'sort_direction' => 'desc',
    ]);

    $html = Blade::render(<<<'BLADE'
        <x-ui.table-toolbar
            action="/records"
            form-id="recordsToolbar"
            :filter-count="1"
            :sort-options="['name' => 'Name', 'created' => 'Created Date']"
            default-sort="created">
            <x-slot:hidden><input type="hidden" name="tab" value="active"></x-slot:hidden>
            <x-slot:filters>
                <x-ui.table-toolbar-field label="Status" name="status">
                    <option value="">All Statuses</option>
                    <option value="Active" @selected(request('status') === 'Active')>Active</option>
                </x-ui.table-toolbar-field>
            </x-slot:filters>
        </x-ui.table-toolbar>
    BLADE);

    expect($html)
        ->toContain('data-table-toolbar="true"')
        ->toContain('data-table-tool-trigger="search"')
        ->toContain('data-table-tool-trigger="filter"')
        ->toContain('data-table-tool-trigger="sort"')
        ->toContain('name="search" value="GCT-101"')
        ->toContain('name="tab" value="active"')
        ->toContain('value="Active" selected')
        ->toContain('value="name" selected')
        ->toContain('value="desc" selected')
        ->toContain('data-table-filter-count')
        ->toContain('>1</span>');
});

it('keeps the toolbar generic and supports forms without filters', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-ui.table-toolbar
            action="/records"
            form-id="recordsToolbar"
            :sort-options="['created' => 'Created Date']"
            default-sort="created" />
    BLADE);

    expect($html)
        ->toContain('data-table-tool-trigger="search"')
        ->toContain('data-table-tool-trigger="sort"')
        ->not->toContain('data-table-tool-trigger="filter"')
        ->not->toContain('data-bus-tool');
});

it('supports filter-only record controls without rendering an irrelevant search control', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-ui.table-toolbar
            action="/records"
            form-id="filterOnlyToolbar"
            :show-search="false">
            <x-slot:filters>
                <x-ui.table-toolbar-field label="Status" name="status">
                    <option value="">All Statuses</option>
                </x-ui.table-toolbar-field>
            </x-slot:filters>
        </x-ui.table-toolbar>
    BLADE);

    expect($html)
        ->toContain('data-table-tool-trigger="filter"')
        ->not->toContain('data-table-tool-trigger="search"')
        ->not->toContain('data-table-tool-trigger="sort"');
});

it('passes explicit data to reusable filter partials', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-ui.table-toolbar
            action="/records"
            filter-view="Operation.Scheduling_And_Dispatch.partials.trip-schedule-filters"
            :filter-data="[
                'selectedTripDate' => '2026-10-13',
                'activeRoutes' => collect([(object) ['id' => 7, 'route_name' => 'Test Route']]),
            ]" />
    BLADE);

    expect($html)
        ->toContain('name="trip_date" value="2026-10-13"')
        ->toContain('value="7"')
        ->toContain('Test Route')
        ->toContain('name="direction"')
        ->toContain('name="status"');
});

it('keeps table toolbar filter fields excluded from legacy inline toolbar label suppression rules', function (): void {
    $toolbarCleanupCss = file_get_contents(resource_path('css/Main-styles/toolbar-cleanup.css'));
    $tableToolbarCss = file_get_contents(resource_path('css/Main-styles/table-toolbar.css'));

    expect($toolbarCleanupCss)
        ->toContain(':not(.gct-table-toolbar)')
        ->toContain(':not(.gct-table-filter-field)');

    expect($tableToolbarCss)
        ->toContain('.gct-table-filter-fields{display:grid!important;gap:12px!important}')
        ->toContain('.gct-table-filter-field{display:grid!important;gap:6px!important}')
        ->toContain('.gct-table-filter-field>span:first-child{display:block!important');
});
