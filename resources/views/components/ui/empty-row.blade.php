@props([
  'colspan' => 1,
  'message' => 'No records found.',
])

<tr {{ $attributes }}>
  <td colspan="{{ $colspan }}" class="empty-row">
    {{ $message }}
  </td>
</tr>
