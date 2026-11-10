@php
    // Allow old usage: label may contain "Title - Price"
    $rawLabel = null;

    if (isset($label)) {
        $rawLabel = $label;
    } else {
        // slot fallback (keep compatibility)
        $rawLabel = trim((string) $slot);
    }

    $titleText = $rawLabel;
    $priceText = '';

    if (is_string($rawLabel) && str_contains($rawLabel, ' - ')) {
        [$titleText, $priceText] = array_pad(explode(' - ', $rawLabel, 2), 2, '');
        $titleText = trim($titleText);
        $priceText = trim($priceText);
    }
@endphp

<label
    for="{{ $id ?? $name }}"
    class="
        w-full
        rounded-xl
        border border-neutral
        bg-background-secondary/20
        px-4 py-4
        cursor-pointer
        transition
        hover:bg-background-secondary/35
        flex items-start justify-between gap-4
        {{ $divClass ?? '' }}
    "
>
    <div class="flex items-start gap-3 min-w-0">
        <input
            type="checkbox"
            name="{{ $name }}"
            id="{{ $id ?? $name }}"
            {{ $attributes->except(['label', 'name', 'id', 'class', 'divClass', 'required']) }}
            class="
                mt-1
                form-checkbox
                size-4
                rounded
                bg-background-secondary
                border-neutral
                text-primary
                focus:ring-2 focus:ring-primary/40
                focus:ring-offset-2 focus:ring-offset-background
                cursor-pointer
            "
        />

        <div class="min-w-0">
            <div class="text-sm font-semibold text-primary-100 truncate">
                {{ $titleText }}
            </div>

            {{-- Description line (blank for now to match spacing) --}}
            <div class="mt-1 text-xs text-primary-500">
                &nbsp;
            </div>
        </div>
    </div>

    {{-- Right side price --}}
    @if(!empty($priceText))
        <div class="shrink-0 text-sm font-semibold text-primary-100">
            {{ $priceText }}
        </div>
    @else
        <div class="shrink-0 text-sm text-transparent select-none">
            .
        </div>
    @endif

    @error($name)
        <p class="text-red-500 text-xs">{{ $message }}</p>
    @enderror
</label>
