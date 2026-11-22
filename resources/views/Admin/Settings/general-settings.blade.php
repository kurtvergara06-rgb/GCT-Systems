<x-layout.app title="FROMS - General Settings" :assets="['resources/css/Admin/Settings/general-settings.css']">
    <div class="app">
        <x-layout.sidebar department="Admin" />
        <main class="main general-settings-page">
            <x-layout.topbar title="General Settings" subtitle="Manage organization configuration and system services" />
            @if(session('success'))
                <p role="status" class="settings-section">{{ session('success') }}</p>
            @endif
            @if($errors->any())
                <div role="alert" class="settings-section"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <section class="gs-dashboard">
                @php
                    $setupFields = [
                        'Organization Name' => $settings->organization_name,
                        'Organization Email' => $settings->organization_email,
                        'Contact Number' => $settings->organization_phone,
                        'Organization Address' => $settings->organization_address,
                    ];
                    $completedFields = collect($setupFields)->filter(fn ($value) => filled($value))->count();
                    $completion = $completedFields * 25;
                @endphp
                <aside class="gs-profile settings-section">
                    <div class="gs-profile-art">
                        <img class="gs-company-logo" src="{{ $settings->organization_logo ?: asset('img/gct_logo.png') }}" alt="{{ $settings->organization_name }} logo" width="140" height="140">
                    </div>
                    @if($canEdit)
                        <div class="gs-logo-upload">
                            <label for="organization_logo"><i class="fa-solid fa-camera"></i> Change Company Logo</label>
                            <input id="organization_logo" name="organization_logo" type="file" accept="image/png,image/jpeg,image/webp" form="organization-settings-form">
                            <small>PNG, JPG or WebP · Maximum 2 MB</small>
                        </div>
                    @endif
                    <h2>{{ $settings->organization_name }}</h2><p>Organization</p>
                    <div class="gs-profile-detail"><i class="fa-solid fa-user-shield"></i><div><strong>{{ $settings->updated_by_name ?? 'Not yet saved' }}</strong><small>Last updated by</small></div></div>
                    <div class="gs-profile-detail"><i class="fa-solid fa-calendar"></i><div><strong>{{ $settings->updated_at?->copy()->timezone('Asia/Manila')->format('M d, Y · h:i A') ?? 'Not yet saved' }}</strong><small>Last configuration update</small></div></div>
                    <div class="gs-completion"><h3>Configuration Status</h3><div class="gs-progress-row"><div class="gs-ring" style="--completion: {{ $completion }}%"><strong>{{ $completion }}%</strong></div><div><strong>{{ $completion === 100 ? 'Details complete' : 'Setup incomplete' }}</strong><small>{{ $completedFields }} of 4 organization fields filled.</small></div></div></div>
                    <div class="gs-setup-checklist">
                        <h3>Setup Checklist</h3>
                        <ul>
                            @foreach($setupFields as $label => $value)
                                <li>
                                    <i class="fa-{{ filled($value) ? 'solid fa-circle-check' : 'regular fa-circle' }}" data-complete="{{ filled($value) ? 'yes' : 'no' }}" aria-hidden="true"></i>
                                    <span>{{ $label }}</span>
                                    <small data-complete="{{ filled($value) ? 'yes' : 'no' }}">{{ filled($value) ? 'Completed' : 'Missing' }}</small>
                                </li>
                            @endforeach
                        </ul>
                        <p class="gs-setup-note"><i class="fa-solid {{ $completion === 100 ? 'fa-circle-check' : 'fa-circle-info' }}" aria-hidden="true"></i><span>{{ $completion === 100 ? 'All organization details are complete.' : 'Complete the missing organization details to finish setup.' }}</span></p>
                    </div>
                </aside>
                <div class="gs-panels">
                    <section id="organization" class="settings-section"><div class="gs-org-header">
                        <div class="settings-section-heading"><div class="section-heading-icon"><i class="fa-solid fa-building"></i></div><div><span>Organization</span><h2>Organization Information</h2><p>Manage the organization's official contact details.</p></div></div>

                                                        @if($canEdit)
                                <div class="gs-actions">
                                    <button class="reset-button" type="reset" form="organization-settings-form"><i class="fa-solid fa-rotate-left"></i> Reset Form</button>
                                    <button class="save-button" type="submit" form="organization-settings-form"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                                </div>
                                @endif
</div>
                        <form id="organization-settings-form" method="POST" enctype="multipart/form-data" action="{{ route('admin.settings.general.update') }}" data-no-partial-navigation>
                            @csrf
                            @method('PUT')
                            <fieldset @disabled(!$canEdit) style="border:0;padding:0;margin:0;min-width:0">
                                <div class="gs-fields">
                                    <div class="form-group"><label for="organization_name">Organization Name</label><input id="organization_name" name="organization_name" required maxlength="150" value="{{ old('organization_name', $settings->organization_name) }}"></div>
                                    <div class="form-group"><label for="organization_email">Organization Email</label><input type="email" id="organization_email" name="organization_email" maxlength="254" value="{{ old('organization_email', $settings->organization_email) }}"></div>
                                    <div class="form-group"><label for="organization_phone">Contact Number</label><input type="tel" id="organization_phone" name="organization_phone" maxlength="50" value="{{ old('organization_phone', $settings->organization_phone) }}"></div>
                                    <div class="form-group"><label for="organization_address">Organization Address</label><textarea id="organization_address" name="organization_address" maxlength="1000" rows="2">{{ old('organization_address', $settings->organization_address) }}</textarea></div>
                                </div>
                            </fieldset>
                        </form>
                    </section>
                    <div class="gs-details-stack">
                    <section id="regional" class="settings-section">
                        <div class="settings-section-heading"><div class="section-heading-icon regional"><i class="fa-solid fa-globe"></i></div><div><span>Regional</span><h2>Regional Configuration</h2><p>Regional standards and time zones.</p></div></div>
                        <div class="gs-info-list">
                            @foreach(['Operational Time Zone'=>'Asia/Manila (UTC+08:00)', 'Application Time Zone'=>config('app.timezone'), 'Currency'=>'PHP (₱)', 'Language'=>config('app.locale') === 'en' ? 'English' : config('app.locale')] as $label=>$value)
                                <div class="gs-info-row"><strong><i class="fa-solid fa-{{ (['Operational Time Zone'=>'clock','Application Time Zone'=>'globe','Currency'=>'coins','Language'=>'language','System Name'=>'desktop','System Full Name'=>'tag','Laravel Version'=>'code','Environment'=>'server','Last Configuration Update'=>'calendar','Updated By'=>'user-shield'])[$label] ?? 'circle-info' }}" aria-hidden="true"></i><span>{{ $label }}</span></strong><span>{{ $value }}</span></div>
                            @endforeach
                        </div>
                    </section>
                    <section id="information" class="settings-section">
                        <div class="settings-section-heading"><div class="section-heading-icon"><i class="fa-solid fa-circle-info"></i></div><div><span>Application</span><h2>System Information</h2><p>Application and environment details.</p></div></div>
                        <div class="gs-info-list">
                            @foreach(['System Name'=>'FROMS','System Full Name'=>'Fleet Resource Optimization and Management System','Laravel Version'=>app()->version(),'Environment'=>app()->environment()] as $label=>$value)
                                <div class="gs-info-row"><strong><i class="fa-solid fa-{{ (['Operational Time Zone'=>'clock','Application Time Zone'=>'globe','Currency'=>'coins','Language'=>'language','System Name'=>'desktop','System Full Name'=>'tag','Laravel Version'=>'code','Environment'=>'server','Last Configuration Update'=>'calendar','Updated By'=>'user-shield'])[$label] ?? 'circle-info' }}" aria-hidden="true"></i><span>{{ $label }}</span></strong><span>{{ $value }}</span></div>
                            @endforeach
                        </div>
                    </section>
                    </div>
                    <section id="services" class="settings-section" data-general-service-panel data-service-url="{{ route('admin.settings.general.services') }}">
                        <div class="settings-section-heading"><div class="section-heading-icon display"><i class="fa-solid fa-server"></i></div><div><span>Integrations</span><h2>System Services</h2><p>Service reachability and heartbeat monitoring; not an end-to-end workflow test.</p></div></div>
                        <div class="gs-service-grid">
                            @foreach(['python'=>'Python ML Engine','nlp'=>'GPS NLP Enrichment','database'=>'Database','reverb'=>'Realtime / Reverb','email'=>'Email Service','scheduler'=>'Task Scheduler'] as $key=>$label)
                                <div class="gs-service-tile"><strong><i class="fa-solid fa-{{ (['python'=>'microchip','nlp'=>'file-lines','database'=>'database','reverb'=>'tower-broadcast','email'=>'envelope','scheduler'=>'calendar-check'])[$key] }}" aria-hidden="true"></i><span>{{ $label }}</span></strong><span data-service-key="{{ $key }}" data-state="neutral">Checking…</span></div>
                            @endforeach
                        </div>
                        <p data-email-service-message role="status"></p>
                        <div class="gs-service-footer">
                            <div class="gs-monitor-meta"><span><i class="fa-solid fa-rotate" aria-hidden="true"></i> Auto-refresh: 60s</span><button type="button" class="gs-refresh-service" data-check-services aria-label="Refresh service statuses" title="Refresh service statuses"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></button></div>
                            <p data-service-message role="status">Checking services…</p>
                        </div>
                    </section>
                    
                </div>
            </section>
        </main>
    </div>
    <script>
        (() => {
            const panel = document.querySelector('[data-general-service-panel]');
            if (!panel || panel.dataset.bound) return;
            panel.dataset.bound = 'true';
            const button = panel.querySelector('[data-check-services]');
            const message = panel.querySelector('[data-service-message]');
            let timer = null, request = null, disposed = false;
            const stopTimer = () => { clearTimeout(timer); timer = null; };
            const schedule = () => {
                stopTimer();
                if (!disposed && panel.isConnected && !document.hidden) timer = setTimeout(check, 60000);
            };
            async function check() {
                if (disposed || !panel.isConnected || document.hidden || request) return;
                stopTimer();
                const controller = new AbortController();
                request = controller;
                button.disabled = true;
                message.textContent = 'Checking services…';
                const timeout = setTimeout(() => controller.abort(), 30000);
                try {
                    const response = await fetch(panel.dataset.serviceUrl, {
                        headers: {'Accept': 'application/json'}, cache: 'no-store', signal: controller.signal
                    });
                    if (!response.ok) throw new Error('Status request failed');
                    const data = await response.json();
                    if (disposed || !panel.isConnected) return;
                    panel.querySelectorAll('[data-service-key]').forEach(row => {
                        const raw = String(data[row.dataset.serviceKey] ?? 'Unknown');
                        const online = /^(reachable|connected|online)$/i.test(raw);
                        row.textContent = online ? 'Online' : /^unavailable$/i.test(raw) ? 'Offline' : raw;
                        row.dataset.state = online ? 'ok' : /^(unavailable|offline)$/i.test(raw) ? 'error' : 'neutral';
                    });
                    panel.querySelector('[data-email-service-message]').textContent = String(data.email_message ?? '');
                    const checked = new Date(data.checked_at);
                    message.textContent = Number.isFinite(checked.getTime())
                        ? 'Last checked: ' + checked.toLocaleTimeString('en-PH', {timeZone:'Asia/Manila', hour:'numeric', minute:'2-digit', second:'2-digit'})
                        : 'Service status updated.';
                } catch {
                    if (disposed || !panel.isConnected || document.hidden) return;
                    panel.querySelectorAll('[data-service-key]').forEach(row => {
                        row.textContent = 'Unknown'; row.dataset.state = 'neutral';
                    });
                    message.textContent = 'Unable to verify services. Retrying automatically.';
                    panel.querySelector('[data-email-service-message]').textContent = '';
                } finally {
                    clearTimeout(timeout);
                    request = null;
                    button.disabled = false;
                    schedule();
                }
            }
            function visibilityChanged() {
                if (document.hidden) { stopTimer(); }
                else if (panel.isConnected) { check(); }
            }
            function dispose() {
                disposed = true;
                stopTimer();
                request?.abort();
                observer.disconnect();
                document.removeEventListener('visibilitychange', visibilityChanged);
                window.removeEventListener('pagehide', pageHidden);
                delete panel.dataset.bound;
            }
            function pageHidden(event) {
                if (!event.persisted) dispose();
                else stopTimer();
            }
            const observer = new MutationObserver(() => { if (!panel.isConnected) dispose(); });
            observer.observe(document.body, {childList:true, subtree:true});
            button.addEventListener('click', check);
            document.addEventListener('visibilitychange', visibilityChanged);
            window.addEventListener('pagehide', pageHidden);
            check();
        })();
    </script>
</x-layout.app>
