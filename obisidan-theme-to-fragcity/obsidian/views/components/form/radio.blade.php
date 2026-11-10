@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'multiple' => false,
    'required' => false,
    'divClass' => null,
    'hideRequiredIndicator' => false,
])

<fieldset class="flex flex-col w-full {{ $divClass ?? '' }}">
    @if ($label)
        <label class="mb-3 text-sm font-semibold text-primary-100">
            {{ $label }}
            @if ($required && !$hideRequiredIndicator)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @if (count($options) == 0 && $slot)
            {{ $slot }}
        @else
            @foreach ($options as $key => $option)
                @php
                    // Supports:
                    // - associative: value => ['title' => '', 'sub' => '', 'price' => '']
                    // - associative: value => "Label"
                    // - array of strings
                    $value = gettype($options) == 'array' ? $option : $key;

                    $title = is_array($option) ? ($option['title'] ?? ($option['label'] ?? '')) : $option;
                    $sub   = is_array($option) ? ($option['sub'] ?? '') : '';
                    $price = is_array($option) ? ($option['price'] ?? '') : '';

                    $isChecked = ($multiple && $selected)
                        ? in_array($key, $selected)
                        : ($selected == $option);

                    $id = $name . '_' . $key;

                    // Icon lookup: exact match "{Title}.svg" in /public/assets/images/
                    $iconFile = is_string($title) ? ($title . '.svg') : '';
                    $iconRelPath = 'assets/images/' . $iconFile;
                    $iconAbsPath = public_path($iconRelPath);
                    $hasIcon = !empty($iconFile) && file_exists($iconAbsPath);
                @endphp

                <label
                    for="{{ $id }}"
                    class="
                        relative overflow-hidden
                        rounded-xl
                        border border-neutral
                        bg-background-secondary/20
                        px-4 py-4
                        cursor-pointer
                        transition
                        hover:bg-background-secondary/35
                        min-h-[88px]
                    "
                >
                    <input
                        type="radio"
                        id="{{ $id }}"
                        name="{{ $name }}"
                        value="{{ is_array($option) ? $key : $value }}"
                        {{ $isChecked ? 'checked' : '' }}
                        {{ $attributes->except(['options','id','name','multiple','class','divClass','label','required','hideRequiredIndicator','selected']) }}
                        class="peer sr-only"
                    />

                    {{-- selected background tint --}}
                    <div class="absolute inset-0 bg-primary/20 opacity-0 peer-checked:opacity-100 transition" aria-hidden="true"></div>

                    {{-- selected border --}}
                    <div class="absolute inset-0 rounded-xl ring-1 ring-transparent peer-checked:ring-primary transition" aria-hidden="true"></div>

                    <div class="relative flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-primary-100 truncate">
                                {{ $title }}
                            </div>

                            <div class="mt-1 text-xs text-primary-500">
                                @if(!empty($sub))
                                    {{ $sub }}
                                @else
                                    &nbsp;
                                @endif
                            </div>

                            <div class="mt-2 text-xs text-primary-100/80">
                                @if(!empty($price))
                                    {{ $price }}
                                @else
                                    &nbsp;
                                @endif
                            </div>
                        </div>

                        {{-- right side: icon + selection indicator --}}
                        <div class="shrink-0 flex items-center gap-3">
                            {{-- icon box --}}
                            <div class="h-10 w-10 rounded-lg border border-neutral bg-background-secondary/30 flex items-center justify-center overflow-hidden">
                                @if($hasIcon)
                                    <img
                                        src="{{ asset($iconRelPath) }}"
                                        alt="{{ $title }}"
                                        class="h-7 w-7 object-contain"
                                    />
                                @endif
                            </div>

                            {{-- selection dot --}}
                            <div class="h-5 w-5 rounded-full border border-neutral flex items-center justify-center
                                peer-checked:border-primary peer-checked:bg-primary/20 transition">
                                <div class="h-2.5 w-2.5 rounded-full bg-transparent peer-checked:bg-primary transition"></div>
                            </div>
                        </div>
                    </div>
                </label>
            @endforeach
        @endif
    </div>

    @error($name)
        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
    @enderror
</fieldset>
