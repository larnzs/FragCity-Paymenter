{{-- resources/views/category/index.blade.php --}}

<div class="container mt-24 pb-24">
    {{-- Header --}}
    <div class="mb-10 text-center">
        <h1 class="text-4xl font-semibold tracking-tight text-base">
            {{ $category->name }}
        </h1>

        @if(!empty($category->description))
            <div class="mx-auto mt-3 max-w-2xl prose dark:prose-invert text-base/60">
                {!! $category->description !!}
            </div>
        @endif
    </div>

    {{-- Category pills --}}
    <div class="mb-14 flex flex-wrap justify-center gap-3">
        @foreach ($categories as $ccategory)
            <a
                href="{{ route('category.show', ['category' => $ccategory->slug]) }}"
                wire:navigate
                class="rounded-full px-5 py-2 text-sm font-medium transition
                    {{ $category->id === $ccategory->id
                        ? 'bg-background-secondary text-base border border-neutral'
                        : 'text-base/60 hover:text-base hover:bg-background-secondary/40 border border-transparent' }}"
            >
                {{ $ccategory->name }}
            </a>
        @endforeach
    </div>

    {{-- Child categories --}}
    @if (isset($childCategories) && count($childCategories) >= 1)
        <div class="mx-auto mb-16 grid max-w-6xl gap-6 sm:grid-cols-2 lg:grid-cols-3 justify-items-center">
            @foreach ($childCategories as $childCategory)
                <a
                    href="{{ route('category.show', ['category' => $childCategory->slug]) }}"
                    wire:navigate
                    class="w-full max-w-xl rounded-3xl border border-neutral
                           bg-background-secondary/40 p-6 transition hover:bg-background-secondary/70"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-base">
                                {{ $childCategory->name }}
                            </h2>

                            @if(!empty($childCategory->description))
                                <div class="mt-2 text-sm text-base/60 prose dark:prose-invert line-clamp-2">
                                    {!! $childCategory->description !!}
                                </div>
                            @endif
                        </div>

                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full
                                     bg-background-secondary/60 border border-neutral text-base/70">
                            &rarr;
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- PRODUCTS --}}
    <div class="mx-auto grid max-w-6xl gap-8 sm:grid-cols-2 justify-items-center">
        @forelse ($products as $product)
            <div
                class="relative w-full max-w-xl min-h-[260px] overflow-hidden rounded-3xl
                       border border-neutral bg-background-secondary/40 transition
                       hover:bg-background-secondary/70"
            >
                {{-- FULL CARD IMAGE --}}
                @if (!empty($product->image))
                    <img
                        src="{{ Storage::url($product->image) }}"
                        alt="{{ $product->name }}"
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/45 to-black/15"></div>
                @else
                    <div class="absolute inset-0 bg-background-secondary/60"></div>
                @endif

                {{-- Content --}}
                <div class="relative p-7 flex h-full flex-col justify-end">
                    <div>
                        {{-- TITLE --}}
                        <h2 class="text-xl font-semibold text-white">
                            {{ $product->name }}
                        </h2>

                        {{-- DESCRIPTION (ALWAYS ENABLED) --}}
                        @if(!empty($product->description))
                            <div class="mt-3 text-sm text-white/80 prose prose-invert line-clamp-3">
                                {!! $product->description !!}
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-white/60">
                                Starting at
                            </p>
                            <p class="text-2xl font-semibold text-white">
                                {{ $product->price()->formatted->price }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            {{-- View --}}
                            <a
                                href="{{ route('products.show', ['category' => $product->category, 'product' => $product->slug]) }}"
                                wire:navigate
                                class="inline-flex items-center justify-center rounded-full
                                       bg-white px-7 py-3 text-sm font-semibold text-black
                                       transition hover:bg-white/90"
                            >
                                View
                            </a>

                            {{-- Cart --}}
                            @if ($product->stock !== 0 && $product->price()->available)
                                <a
                                    href="{{ route('products.checkout', ['category' => $category, 'product' => $product->slug]) }}"
                                    wire:navigate
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-full
                                           bg-black/40 border border-white/20
                                           transition hover:bg-black/55"
                                    aria-label="Add to cart"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                                         viewBox="0 0 24 24" class="size-5 text-white">
                                        <path d="M7.00488 7.99966V5.99966C7.00488 3.23824 9.24346 0.999664
                                                 12.0049 0.999664C14.7663 0.999664 17.0049 3.23824
                                                 17.0049 5.99966V7.99966H20.0049C20.5572 7.99966
                                                 21.0049 8.44738 21.0049 8.99966V20.9997C21.0049
                                                 21.5519 20.5572 21.9997 20.0049 21.9997H4.00488
                                                 C3.4526 21.9997 3.00488 21.5519 3.00488
                                                 20.9997V8.99966C3.00488 8.44738 3.4526
                                                 7.99966 4.00488 7.99966H7.00488Z"/>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center text-base/60">
                No products available in this category yet.
            </div>
        @endforelse
    </div>
</div>
