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
            <x-layout.topbar title="Trip History" subtitle="Assignment and operational records in one read-only view" />

            <section data-ajax-region="summary" class="trip-summary-grid trip-history-kpis" aria-label="{{ $heading }} overview">
                {{ $kpis }}
            </section>

            <section data-ajax-region="records" class="table-card trip-history-card">
                <div class="section-header trip-history-header">
                    <div><h2>{{ $heading }}</h2><p>{{ $description }}</p></div>
                    @isset($headerAction){{ $headerAction }}@endisset
                </div>

                <div class="trip-history-controls">
                    {{ $toolbar }}
                </div>

                @isset($helper)<div class="trip-history-helper">{{ $helper }}</div>@endisset

                <div class="table-wrap trip-table-wrap trip-history-table-wrap gct-responsive-records gct-sticky-actions">{{ $slot }}</div>
                {{ $pagination }}
            </section>
        </main>
    </div>

    @isset($after){{ $after }}@endisset
</x-layout.app>
