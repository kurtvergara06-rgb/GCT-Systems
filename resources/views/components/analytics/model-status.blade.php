@props([
    'models' => [],
])

@inject('aiModelStatusService', 'App\Services\AiModelStatusService')

@php
    $models = ! empty($models) ? $models : $aiModelStatusService->all();
@endphp

<section class="overview-panel ai-model-status-panel" aria-labelledby="ai-model-status-heading">
    <div class="panel-header">
        <div>
            <span class="panel-kicker">Production ML Readiness</span>
            <h2 id="ai-model-status-heading">AI Model Status</h2>
            <p>
                Live readiness and training-data provenance reported by the Python engine.
                Simulation-ready models can use generated data locally, while production readiness still requires genuine data.
            </p>
        </div>

        <span class="model-policy-badge">
            <i class="fa-solid fa-shield-halved"></i>
            Genuine-data policy
        </span>
    </div>

    <div class="ai-model-status-grid">
        @foreach($models as $model)
            <article class="ai-model-card tone-{{ $model->tone }}" data-model-status="{{ $model->key }}">
                <div class="ai-model-card-top">
                    <div class="ai-model-icon">
                        <i class="fa-solid {{ $model->icon }}"></i>
                    </div>

                    <span class="ai-model-state tone-{{ $model->tone }}">
                        @if($model->tone === 'ready')
                            <i class="fa-solid fa-circle-check"></i>
                        @elseif($model->tone === 'offline')
                            <i class="fa-solid fa-plug-circle-xmark"></i>
                        @elseif($model->tone === 'partial')
                            <i class="fa-solid fa-circle-half-stroke"></i>
                        @else
                            <i class="fa-solid fa-circle-exclamation"></i>
                        @endif
                        {{ $model->state }}
                    </span>
                </div>

                <div class="ai-model-heading">
                    <span>{{ $model->number }}</span>
                    <h3>{{ $model->name }}</h3>
                </div>

                <div class="ai-model-meta">
                    <div>
                        <span>Training source</span>
                        <strong>{{ $model->data_source }}</strong>
                    </div>
                    <div>
                        <span>Training records</span>
                        <strong>{{ number_format($model->sample_count) }}</strong>
                    </div>
                </div>

                <p class="ai-model-dataset">{{ $model->dataset_type }}</p>

                @if(! empty($model->details))
                    <div class="ai-model-details">
                        @foreach($model->details as $detail)
                            <div>
                                <span>{{ $detail->label }}</span>
                                <strong>{{ $detail->value }}</strong>
                                <small>{{ number_format($detail->sample_count) }} records</small>
                            </div>
                        @endforeach
                    </div>
                @endif

                <p class="ai-model-reason">{{ $model->reason }}</p>
            </article>
        @endforeach
    </div>

    @foreach($models as $model)
        @if(! empty($model->candidate_models))
            <section class="model-comparison" aria-labelledby="model-comparison-{{ $model->key }}">
                <div class="model-comparison-heading">
                    <div>
                        <span class="panel-kicker">Validated Candidate Benchmark</span>
                        <h3 id="model-comparison-{{ $model->key }}">{{ $model->name }} Comparison</h3>
                        <p>
                            All candidates use the same {{ str_replace('_', ' ', $model->split_strategy ?: 'reported') }} holdout.
                            Lower MAE and RMSE are better; higher R² is better.
                        </p>
                    </div>

                    <div class="model-comparison-meta">
                        @if($model->selected_model !== '')
                            <span><strong>Selected:</strong> {{ $model->selected_model }}</span>
                        @endif
                        @if($model->model_version !== '')
                            <span><strong>Version:</strong> {{ $model->model_version }}</span>
                        @endif
                    </div>
                </div>

                <div class="model-comparison-table-wrap">
                    <table class="model-comparison-table">
                        <thead>
                            <tr>
                                <th scope="col">Candidate Model</th>
                                <th scope="col">Model Family</th>
                                <th scope="col">MAE (minutes)</th>
                                <th scope="col">RMSE (minutes)</th>
                                <th scope="col">R² Score</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($model->candidate_models as $candidate)
                                <tr class="{{ $candidate->selected ? 'is-selected' : '' }}">
                                    <td>
                                        <span class="candidate-name">
                                            <span class="candidate-dot candidate-dot--{{ $candidate->key }}"></span>
                                            {{ $candidate->name }}
                                        </span>
                                        @if($candidate->selected)
                                            <span class="candidate-selected">Selected</span>
                                        @endif
                                    </td>
                                    <td>{{ $candidate->family }}</td>
                                    <td>{{ $candidate->mae !== null ? number_format($candidate->mae, 2) : '—' }}</td>
                                    <td>{{ $candidate->rmse !== null ? number_format($candidate->rmse, 2) : '—' }}</td>
                                    <td class="{{ $candidate->r2 !== null && $candidate->r2 < 0 ? 'metric-negative' : '' }}">
                                        {{ $candidate->r2 !== null ? number_format($candidate->r2, 3) : '—' }}
                                    </td>
                                    <td>
                                        <span class="candidate-status candidate-status--{{ $candidate->status }}">
                                            {{ $candidate->selected ? 'SELECTED' : strtoupper(str_replace('_', ' ', $candidate->status)) }}
                                        </span>
                                        @if($candidate->reason !== '')
                                            <small title="{{ $candidate->reason }}">{{ $candidate->reason }}</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="model-comparison-source">
                    <i class="fa-solid fa-database"></i>
                    Results reported by the Python training artifact · {{ $model->data_source }} ·
                    {{ number_format($model->sample_count) }} records
                </p>
            </section>
        @endif
    @endforeach

    <div class="ai-model-policy-note">
        <i class="fa-solid fa-circle-info"></i>
        <span>
            <strong>Production rule:</strong>
            a model is marked Ready only when its status endpoint confirms a genuine GCT data source and a usable production model.
            A usable simulated model is marked <strong>Simulation Ready</strong> only when the explicit non-production ML setting is enabled, and remains visibly identified as simulated data.
            Insufficient genuine history in production is shown as <strong>MODEL NOT READY</strong>.
        </span>
    </div>
</section>
