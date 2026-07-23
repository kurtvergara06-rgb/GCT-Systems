@props([
  'id',
  'data' => [],
])

<script
  type="application/json"
  id="{{ $id }}"
>
{!! json_encode(
  $data,
  JSON_HEX_TAG |
  JSON_HEX_AMP |
  JSON_HEX_APOS |
  JSON_HEX_QUOT
) !!}
</script>