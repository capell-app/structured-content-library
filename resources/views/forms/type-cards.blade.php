<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        {{ $getExtraAttributeBag()->grid($getColumns())->class(['fi-fo-radio']) }}
        role="radiogroup"
        aria-label="{{ $getLabel() }}"
    >
        @foreach ($getOptions() as $value => $label)
            <label
                class="fi-fo-radio-label"
                style="padding: 1rem; border: 1px solid currentColor; border-radius: .75rem; height: 100%"
            >
                <input
                    type="radio"
                    class="fi-radio-input"
                    id="{{ $getId() }}-{{ $value }}"
                    name="{{ $getId() }}"
                    value="{{ $value }}"
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
                    @disabled($isDisabled() || $isOptionDisabled($value, $label))
                    @checked($getState() === $value)
                    aria-describedby="{{ $getId() }}-{{ $value }}-description"
                />
                <div class="fi-fo-radio-label-text">
                    <p>{{ $label }}</p>
                    <p
                        class="fi-fo-radio-label-description"
                        id="{{ $getId() }}-{{ $value }}-description"
                    >
                        {{ $getDescription($value) }}
                    </p>
                </div>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
