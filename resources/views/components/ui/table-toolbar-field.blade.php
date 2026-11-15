@props(['label', 'name', 'default' => '', 'type' => 'select', 'value' => null])

<label class="gct-table-filter-field">
    <span>{{ $label }}</span>
    @if($type === 'date')
        <input type="date" name="{{ $name }}" value="{{ $value ?? request($name) }}" data-default="{{ $default }}">
    @else
        <select name="{{ $name }}" data-default="{{ $default }}" {{ $attributes }}>{{ $slot }}</select>
    @endif
</label>
