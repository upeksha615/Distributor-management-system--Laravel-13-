@props(['label', 'name', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="3" class="input" @required($required) {{ $attributes->except('class') }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" class="input" @required($required) {{ $attributes->except('class') }}>
    @endif
    @if ($help)<p class="mt-1 text-xs text-slate-500">{{ $help }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
