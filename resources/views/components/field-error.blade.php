@props(['field'])
@error($field)<p class="field-error" id="{{ str_replace('.', '-', $field) }}-error">{{ $message }}</p>@enderror
