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
                Synthetic or sample artifacts are never treated as production-ready.
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

    <div class="ai-model-policy-note">
        <i class="fa-solid fa-circle-info"></i>
        <span>
            <strong>Production rule:</strong>
            a model is marked Ready only when its status endpoint confirms a genuine GCT data source and a usable production model.
            Insufficient genuine history is shown as <strong>MODEL NOT READY</strong> instead of falling back to synthetic predictions.
        </span>
    </div>
</section>
