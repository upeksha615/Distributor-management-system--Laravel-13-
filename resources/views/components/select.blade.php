@props(['label', 'name', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null])
{{-- options: [value => label] --}}
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" class="input" @required($required) {{ $attributes->except('class') }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
