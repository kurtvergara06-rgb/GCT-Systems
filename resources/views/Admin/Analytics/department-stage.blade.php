<x-layout.app title="FROMS - Department Analytics" :assets="['resources/css/Main-styles/main.css','resources/css/Main-styles/sidebar.css']">
    <div class="app">
        <x-layout.sidebar :department="auth()->user()->department" />
        <main class="main">
            <x-layout.topbar title="{{ ucfirst($stage) }} Analytics" subtitle="{{ ucfirst($department) }} Department · Authorized analytics" />
            <section style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px;max-width:1120px">
                <h2 style="font-size:18px;margin-bottom:6px">{{ ucfirst($department) }} data overview</h2>
                <p style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:18px">Figures below are live counts from authorized departmental records, not estimates or machine-learning outputs. The advanced {{ $stage }} models and recommendations are not yet available in the department-scoped experience.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
                    @forelse($metrics as $metric)
                        <article style="border:1px solid var(--border);border-radius:10px;padding:16px">
                            <h3 style="font-size:13px;color:var(--muted)">{{ $metric['label'] }}</h3>
                            <strong style="display:block;font-size:26px;color:var(--text);margin:8px 0">{{ number_format($metric['total']) }}</strong>
                            <span style="font-size:12px;color:var(--muted)">Total records</span>
                            @foreach($metric['statuses'] as $status)
                                <div style="display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--border);padding-top:8px;margin-top:8px;font-size:12px">
                                    <span>{{ $status->status ?: 'Unspecified' }}</span>
                                    <strong>{{ number_format($status->total) }}</strong>
                                </div>
                            @endforeach
                        </article>
                    @empty
                        <p style="font-size:13px;color:var(--muted)">No available departmental data sources.</p>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
