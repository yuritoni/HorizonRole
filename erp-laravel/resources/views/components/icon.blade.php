@props(['name'])

<i data-lucide="{{ $name }}" {{ $attributes->merge(['class' => 'ui-icon']) }} aria-hidden="true"></i>
