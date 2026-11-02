<x-layout.app title="FROMS - Analytics Access" :assets="['resources/css/Main-styles/main.css','resources/css/Main-styles/sidebar.css']">
    <div class="app">
        <x-layout.sidebar :department="auth()->user()->department" />
        <main class="main">
            <x-layout.topbar title="Analytics Access" subtitle="Your department's assigned analytics capabilities" />
            <section style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:24px;max-width:850px">
                <h2 style="font-size:18px;margin-bottom:10px">Analytics access granted</h2>
                <p style="font-size:13px;color:var(--muted);line-height:1.7">Your role has Analytics access. The department-specific analytics dashboards are being secured for cross-department use. Full Analytics data remains restricted to System Administrators until data-level authorization is verified.</p>
                <div style="margin-top:18px;display:grid;gap:9px">
                    @foreach(['view' => 'View Analytics', 'analyze' => 'Diagnostic & Predictive Analytics', 'recommendations' => 'Prescriptive Recommendations'] as $key => $label)
                        <div style="border:1px solid var(--border);border-radius:8px;padding:12px;font-size:13px;display:flex;justify-content:space-between;gap:8px">
                            <strong>{{ $label }}</strong>
                            <span>{{ $capabilities[$key] ? 'Granted — dashboard pending secure scoping' : 'Not granted' }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
