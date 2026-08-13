<x-layout.app
    title="FROMS - Trip Records"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/Trip_Records/trip-records.css',
        'resources/js/Main-js/sidebar.js',
    ]"
>
    <div class="app">
      <x-layout.sidebar department="Operation" />
        <main class="main trip-records-page">
            <x-layout.topbar
                title="Trip Records"
                subtitle="Review completed shuttle trips and operational history"
                notification-count="4"
            />

            <section class="trip-records-content">
                <h2>Trip Records</h2>
                <p>Trip records content will be rendered here.</p>
            </section>
        </main>
    </div>
</x-layout.app>
