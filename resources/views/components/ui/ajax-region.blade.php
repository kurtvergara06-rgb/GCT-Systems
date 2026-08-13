@props([
    'name',
    'id' => null,
])

@php
    $regionId = $id ?: 'ajax-region-' . \Illuminate\Support\Str::slug($name);
@endphp

<div
    id="{{ $regionId }}"
    data-ajax-region="{{ $name }}"
    {{ $attributes }}
>
    {{ $slot }}
</div>
