@php
    $resourceIdentifier = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($config->name ?? '') . ' ' . (string) ($config->label ?? '')));
    $resourceChildren = collect($config->children ?? []);
    $isResourceSlider = !($config->multiple ?? false)
        && $resourceChildren->count() > 1
        && (
            str_contains($resourceIdentifier, 'ram')
            || str_contains($resourceIdentifier, 'memory')
            || str_contains($resourceIdentifier, 'cpu')
            || str_contains($resourceIdentifier, 'processor')
            || str_contains($resourceIdentifier, 'ssd')
            || str_contains($resourceIdentifier, 'storage')
            || str_contains($resourceIdentifier, 'disk')
            || str_contains($resourceIdentifier, 'playerslot')
        );
    $resourceSliderStopIndexes = [];
    $resourceSliderLastIndex = $resourceChildren->count() - 1;
    if ($resourceSliderLastIndex >= 0) {
        $resourceSliderStep = max(1, (int) ceil($resourceSliderLastIndex / 5));
        for ($resourceSliderIndex = 0; $resourceSliderIndex <= $resourceSliderLastIndex; $resourceSliderIndex += $resourceSliderStep) {
            $resourceSliderStopIndexes[] = $resourceSliderIndex;
        }
        if (end($resourceSliderStopIndexes) !== $resourceSliderLastIndex) {
            $resourceSliderStopIndexes[] = $resourceSliderLastIndex;
        }
    }
@endphp

<div class="fc-checkout-field">
    @switch($config->type)
        @case('select')
            @if ($isResourceSlider)
                <div class="fc-resource-slider-control"
                    x-data="{
                        options: @js($config->children->values()->map(fn($child) => [
                            'label' => $child->name,
                            'value' => $child->id,
                            'price' => ($showPriceTag && $child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available) ? (string) $child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) : ''
                        ])),
                        selectedOption: 0,
                        selectedName: '',
                        selectedPrice: '',
                        backendOption: $wire.entangle('{{ $name }}').live,
                        
                        progressOption: 0,
                        init() {
                        const initialIndex = this.options.findIndex(
                            option => String(option.value) === String(this.backendOption ?? '')
                        );
                        if (initialIndex >= 0) this.selectedOption = initialIndex;
                        this.updateSliderVisuals();

                        // Keep Livewire and Alpine on one source of truth. The local
                        // thumb remains responsive while server updates are debounced.
                        this.$watch('selectedOption', Alpine.debounce((index) => {
                            const selected = this.options[index];
                            if (selected && String(this.backendOption ?? '') !== String(selected.value)) {
                                this.backendOption = selected.value;
                            }
                        }, 140));

                        this.$watch('backendOption', (value) => {
                            const nextIndex = this.options.findIndex(
                                option => String(option.value) === String(value ?? '')
                            );
                            if (nextIndex >= 0 && nextIndex !== this.selectedOption) {
                                this.selectedOption = nextIndex;
                                this.updateSliderVisuals();
                            }
                        });
                    },
                        updateSliderVisuals() {
                            const lastIndex = Math.max(1, this.options.length - 1);
                            const selected = this.options[this.selectedOption] || this.options[0];
                            this.progressOption = this.options.length > 1 ? this.selectedOption / lastIndex : 0;
                            this.selectedName = selected ? selected.label : '';
                            this.selectedPrice = selected ? selected.price : '';
                        },
                        setOptionValue(index) {
                            const parsed = Number.parseInt(index, 10);
                            if (!Number.isInteger(parsed)) return;
                            this.selectedOption = Math.max(0, Math.min(this.options.length - 1, parsed));
                            this.updateSliderVisuals();
                        },
                    }">
                    <div class="fc-resource-slider-control__heading">
                        <label for="{{ $name }}">{{ $config->label ?? $config->name }}</label>
                        <div class="fc-resource-slider-control__current">
                            <strong x-text="selectedName"></strong>
                            <span x-show="selectedPrice" x-text="selectedPrice"></span>
                        </div>
                    </div>

                    <div class="fc-resource-slider-control__track" wire:ignore>
                        <div class="fc-resource-slider-control__rail" aria-hidden="true"></div>
                        <div class="fc-resource-slider-control__progress" :style="'transform:scaleX(' + progressOption + ')'" aria-hidden="true"></div>
                        <input class="fc-resource-slider-control__input" type="range" min="0"
                            max="{{ max(0, $config->children->count() - 1) }}" step="1"
                            :value="selectedOption" @input="setOptionValue($event.target.value)" 
                            id="{{ $name }}" name="{{ $name }}" aria-label="{{ $config->label ?? $config->name }}">
                    </div>

                    <div class="fc-resource-slider-control__stops">
                        @foreach ($resourceSliderStopIndexes as $stopIndex)
                            @php $child = $resourceChildren[$stopIndex]; @endphp
                            <button type="button" class="fc-resource-slider-control__stop"
                                @click="setOptionValue({{ $stopIndex }})"
                                :class="{ 'is-active': selectedOption === {{ $stopIndex }} }"
                                :aria-pressed="selectedOption === {{ $stopIndex }} ? 'true' : 'false'">
                                <span>{{ $child->name }}</span>
                                @if ($showPriceTag && $child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available)
                                    <small>{{ $child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) }}</small>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @else
                <x-form.select name="{{ $name }}" :label="__($config->label ?? $config->name)"
                    :required="$config->required ?? false" :selected="config('configs.' . $config->name)"
                    :multiple="$config->multiple ?? false" wire:model.live="{{ $name }}"
                    :placeholder="$config->placeholder ?? ''">
                    {{ $slot }}
                </x-form.select>
            @endif
        @break

        @case('slider')
            <div class="fc-resource-slider-control"
                x-data="{
                    options: @js($config->children->values()->map(fn($child) => [
                        'label' => $child->name,
                        'value' => $child->id,
                        'price' => ($showPriceTag && $child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available) ? (string) $child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) : ''
                    ])),
                    selectedOption: 0,
                    selectedName: '',
                    selectedPrice: '',
                        backendOption: $wire.entangle('{{ $name }}').live,
                    
                    progressOption: 0,
                    init() {
                        const initialIndex = this.options.findIndex(
                            option => String(option.value) === String(this.backendOption ?? '')
                        );
                        if (initialIndex >= 0) this.selectedOption = initialIndex;
                        this.updateSliderVisuals();

                        // Keep Livewire and Alpine on one source of truth. The local
                        // thumb remains responsive while server updates are debounced.
                        this.$watch('selectedOption', Alpine.debounce((index) => {
                            const selected = this.options[index];
                            if (selected && String(this.backendOption ?? '') !== String(selected.value)) {
                                this.backendOption = selected.value;
                            }
                        }, 140));

                        this.$watch('backendOption', (value) => {
                            const nextIndex = this.options.findIndex(
                                option => String(option.value) === String(value ?? '')
                            );
                            if (nextIndex >= 0 && nextIndex !== this.selectedOption) {
                                this.selectedOption = nextIndex;
                                this.updateSliderVisuals();
                            }
                        });
                    },
                    updateSliderVisuals() {
                        const lastIndex = Math.max(1, this.options.length - 1);
                        const selected = this.options[this.selectedOption] || this.options[0];
                        this.progressOption = this.options.length > 1 ? this.selectedOption / lastIndex : 0;
                        this.selectedName = selected ? selected.label : '';
                        this.selectedPrice = selected ? selected.price : '';
                    },
                    setOptionValue(index) {
                            const parsed = Number.parseInt(index, 10);
                            if (!Number.isInteger(parsed)) return;
                            this.selectedOption = Math.max(0, Math.min(this.options.length - 1, parsed));
                            this.updateSliderVisuals();
                        },
                }">
                <div class="fc-resource-slider-control__heading">
                    <label for="{{ $name }}">{{ $config->label ?? $config->name }}</label>
                    <div class="fc-resource-slider-control__current"><strong x-text="selectedName"></strong><span x-show="selectedPrice" x-text="selectedPrice"></span></div>
                </div>
                <div class="fc-resource-slider-control__track" wire:ignore>
                    <div class="fc-resource-slider-control__rail" aria-hidden="true"></div>
                    <div class="fc-resource-slider-control__progress" :style="'transform:scaleX(' + progressOption + ')'" aria-hidden="true"></div>
                    <input class="fc-resource-slider-control__input" type="range" min="0"
                        max="{{ max(0, $config->children->count() - 1) }}" step="1"
                        :value="selectedOption" @input="setOptionValue($event.target.value)" 
                        id="{{ $name }}" name="{{ $name }}" aria-label="{{ $config->label ?? $config->name }}">
                </div>
                <div class="fc-resource-slider-control__stops">
                    @foreach ($resourceSliderStopIndexes as $stopIndex)
                        @php $child = $resourceChildren[$stopIndex]; @endphp
                        <button type="button" class="fc-resource-slider-control__stop" @click="setOptionValue({{ $stopIndex }})"
                            :class="{ 'is-active': selectedOption === {{ $stopIndex }} }"
                            :aria-pressed="selectedOption === {{ $stopIndex }} ? 'true' : 'false'">
                            <span>{{ $child->name }}</span>
                            @if ($showPriceTag && $child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available)
                                <small>{{ $child->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) }}</small>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @break

        @case('text')
        @case('password')
        @case('email')
        @case('number')
        @case('color')
        @case('file')
            @php
                $isSensitiveConfigOption = strtoupper((string) ($config->env_variable ?? '')) === 'STEAM_ACC';
                $configInputName = $isSensitiveConfigOption ? 'sensitiveConfigOptions.' . $config->id : $name;
            @endphp
            <x-form.input name="{{ $configInputName }}"
                :type="$isSensitiveConfigOption ? 'password' : $config->type"
                :label="__($config->label ?? $config->name)"
                :required="$config->required ?? false"
                wire:model.blur="{{ $configInputName }}"
                :placeholder="$config->placeholder ?? ($config->default ?? '')" />
        @break

        @case('checkbox')
            <x-form.checkbox name="{{ $name }}" type="checkbox"
                :label="__($config->label ?? $config->name) . (($showPriceTag && $config->children->first()->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available) ? ' - ' . $config->children->first()->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) : '')"
                :required="$config->required ?? false" wire:model.live="{{ $name }}" />
        @break

        @case('radio')
            <x-form.radio name="{{ $name }}" :label="__($config->label ?? $config->name)"
                :required="$config->required ?? false" wire:model.live="{{ $name }}">
                {{ $slot }}
            </x-form.radio>
        @break

        @default
    @endswitch

    @isset($config->description)
        @isset($config->link)
            <a href="{{ $config->link }}" class="fc-checkout-field__description-link">
                {{ $config->description }}
                <x-ri-arrow-right-long-line class="ml-1 size-3 inline-block -rotate-45 group-hover:rotate-0 transition" />
            </a>
        @else
            <p class="fc-checkout-field__description">{{ $config->description }}</p>
        @endisset
    @endisset
</div>
