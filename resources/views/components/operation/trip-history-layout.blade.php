@props(['activeView', 'heading', 'description'])

<x-layout.app
    title="FROMS - Trip History"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Trip_Records/trip-records.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Trip_Records/trip-records.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Operation" />
        <main class="main trip-records-page">
            <x-layout.topbar title="Trip History" subtitle="Scheduling outcomes and operational records" />

            <section data-ajax-region="summary" class="trip-summary-grid trip-history-kpis" aria-label="{{ $heading }} overview">
                {{ $kpis }}
            </section>

            <section data-ajax-region="records" class="table-card trip-history-card">
                <div class="section-header trip-history-header">
                    <div><h2>{{ $heading }}</h2><p>{{ $description }}</p></div>
                    @isset($headerAction){{ $headerAction }}@endisset
                </div>

                <div class="trip-history-controls">
                    <nav class="trip-history-tabs" aria-label="Trip history views">
                        <a href="{{ route('trip-records', ['view' => 'assignments'], false) }}" class="trip-history-tab {{ $activeView === 'assignments' ? 'is-active' : '' }}" @if($activeView === 'assignments') aria-current="page" @endif>
                            <i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Assignment History
                        </a>
                        <a href="{{ route('trip-records', ['view' => 'operations'], false) }}" class="trip-history-tab {{ $activeView === 'operations' ? 'is-active' : '' }}" @if($activeView === 'operations') aria-current="page" @endif>
                            <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Operational Records
                        </a>
                    </nav>
                    {{ $toolbar }}
                </div>

                @isset($helper)<div class="trip-history-helper">{{ $helper }}</div>@endisset

                <div class="table-wrap trip-table-wrap trip-history-table-wrap">{{ $slot }}</div>
                {{ $pagination }}
            </section>
        </main>
    </div>

    @isset($after){{ $after }}@endisset
</x-layout.app>
