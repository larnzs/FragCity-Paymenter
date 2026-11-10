<div class="flex flex-col gap-1">
    @switch($config->type)
        @case('select')
            <x-form.select
                name="{{ $name }}"
                :label="__($config->label ?? $config->name)"
                :required="$config->required ?? false"
                :selected="config('configs.' . $config->name)"
                :multiple="$config->multiple ?? false"
                wire:model.live="{{ $name }}"
                :placeholder="$config->placeholder ?? ''"
            >
                {{ $slot }}
            </x-form.select>
        @break

        @case('slider')
            <div x-data="{
                options: @js(
                    $config->children->map(fn($child) => [
                        'option' => $child->name,
                        'value'  => $child->id,
                        'price'  => (
                            isset($plan)
                            && isset($showPriceTag)
                            && $showPriceTag
                            && $child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available
                        )
                            ? (string)$child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)
                            : ''
                    ])
                ),
                showPriceTag: @js($showPriceTag ?? false),
                selectedOption: 0,
                backendOption: $wire.entangle('{{ $name }}').live,
                progressOption: '0%',
                segmentsWidthOption: '0%',

                init() {
                    const initialValue = this.$wire.get('{{ $name }}');
                    const foundIndex = this.options.findIndex(opt => opt.value == initialValue);
                    if (foundIndex !== -1) {
                        this.selectedOption = foundIndex;
                    }
                    this.updateSliderVisuals();
                    $watch('selectedOption', Alpine.debounce(() => {
                        this.backendOption = this.options[this.selectedOption].value;
                    }, 150));
                },

                updateSliderVisuals() {
                    this.progressOption = `${(this.selectedOption / (this.options.length - 1)) * 100}%`;
                    this.segmentsWidthOption = `${100 / (this.options.length - 1)}%`;
                },

                setOptionValue(index) {
                    this.selectedOption = parseInt(index);
                    this.updateSliderVisuals();
                }
            }" class="flex flex-col gap-2 relative">
                <div class="flex items-center justify-between">
                    <label for="{{ $name }}" class="text-sm font-semibold text-primary-100">
                        {{ $config->label ?? $config->name }}
                    </label>

                    <div class="text-sm font-semibold text-primary-100">
                        <span x-text="options[selectedOption]?.option"></span>
                    </div>
                </div>

                <div class="relative flex items-center" :style="`--progress:${progressOption};--segments-width:${segmentsWidthOption}`" wire:ignore>
                    <div class="absolute left-2.5 right-2.5 h-1.5 bg-background-secondary rounded-full overflow-hidden">
                        <div
                            class="absolute inset-y-0 left-0 bg-primary transition-all duration-300 ease-out"
                            :style="`width: var(--progress)`"
                            aria-hidden="true"
                        ></div>
                    </div>

                    <input
                        class="
                            relative appearance-none cursor-pointer w-full bg-transparent focus:outline-none
                            [&::-webkit-slider-thumb]:appearance-none
                            [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4
                            [&::-webkit-slider-thumb]:rounded-full
                            [&::-webkit-slider-thumb]:bg-primary
                            [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-background
                            [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4
                            [&::-moz-range-thumb]:rounded-full
                            [&::-moz-range-thumb]:bg-primary
                            [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-background
                        "
                        type="range"
                        min="0"
                        :max="options.length - 1"
                        x-model="selectedOption"
                        @input="setOptionValue(selectedOption)"
                        aria-label="Option Slider"
                        name="{{ $name }}"
                        id="{{ $name }}"
                    />
                </div>

                {{-- step labels (kept, but toned down so it won't "explode" the layout) --}}
                <ul class="flex justify-between px-2.5 pt-2 text-xs text-primary-500">
                    @foreach($config->children as $child)
                        <li class="relative flex-1">
                            <span class="block text-center truncate">
                                {{ $child->name }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @break

        @case('text')
        @case('password')
        @case('email')
        @case('number')
        @case('color')
        @case('file')
            <x-form.input
                name="{{ $name }}"
                :type="$config->type"
                :label="__($config->label ?? $config->name)"
                :placeholder="$config->default ?? ''"
                :required="$config->required ?? false"
                wire:model.live="{{ $name }}"
                :placeholder="$config->placeholder ?? ''"
            />
        @break

        @case('checkbox')
            <x-form.checkbox
                name="{{ $name }}"
                type="checkbox"
                :label="__($config->label ?? $config->name) . (
                    (isset($plan) && isset($showPriceTag) && $showPriceTag && $config->children->first()?->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available)
                        ? ' - ' . $config->children->first()->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)
                        : ''
                )"
                :required="$config->required ?? false"
                wire:model.live="{{ $name }}"
            />
        @break

        @case('radio')
            @php
                /**
                 * Build tile options for radio.blade.php.
                 * Supports:
                 * - product config options: $config->children (each has id, name, price)
                 * - checkout config: $config->options (value => label)
                 *
                 * radio.blade.php supports:
                 *   value => ['title' => '', 'sub' => '', 'price' => '']
                 */
                $radioOptions = [];

                // Product config options
                if (isset($config->children)) {
                    foreach ($config->children as $child) {
                        $priceLine = '';
                        if (isset($plan) && isset($showPriceTag) && $showPriceTag) {
                            $priceObj = $child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit);
                            if ($priceObj->available) {
                                $priceLine = (string) $priceObj;
                            }
                        }

                        $radioOptions[$child->id] = [
                            'title' => $child->name,
                            // Leave blank unless you later want to map descriptions here
                            'sub'   => '',
                            'price' => $priceLine,
                        ];
                    }
                }

                // Checkout config (admin enabled) value => label
                if (empty($radioOptions) && isset($config->options) && is_array($config->options)) {
                    foreach ($config->options as $val => $labelText) {
                        $radioOptions[$val] = [
                            'title' => (string) $labelText,
                            'sub'   => '',
                            'price' => '',
                        ];
                    }
                }
            @endphp

            <x-form.radio
                name="{{ $name }}"
                :label="__($config->label ?? $config->name)"
                :required="$config->required ?? false"
                :options="$radioOptions"
                wire:model.live="{{ $name }}"
            />
        @break

        @default
    @endswitch

    @isset($config->description)
        @isset($config->link)
            <a href="{{ $config->link }}" class="text-xs text-primary-500 hover:underline hover:text-secondary group">
                {{ $config->description }}
                <x-ri-arrow-right-long-line class="ml-1 size-3 inline-block -rotate-45 group-hover:rotate-0 transition" />
            </a>
        @else
            <p class="text-xs text-primary-500">{{ $config->description }}</p>
        @endisset
    @endisset
</div>
