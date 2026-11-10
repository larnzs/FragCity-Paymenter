{{-- resources/views/products/show.blade.php --}}

<div class="container mt-24 pb-24">
    {{-- Back --}}
    <div class="mb-10 flex justify-center">
        <a
            href="{{ route('category.show', ['category' => $category->slug]) }}"
            wire:navigate
            class="text-sm text-base/60 hover:text-base transition"
        >
            &larr; Back to {{ $category->name }}
        </a>
    </div>

    <div class="mx-auto max-w-6xl">
        <div class="relative overflow-hidden rounded-3xl border border-neutral bg-background-secondary/40">
            {{-- SINGLE IMAGE (FULL CARD BACKGROUND) --}}
            @if (!empty($product->image))
                <img
                    src="{{ Storage::url($product->image) }}"
                    alt="{{ $product->name }}"
                    class="absolute inset-0 h-full w-full object-cover"
                />
                {{-- Darken for readability --}}
                <div class="absolute inset-0 bg-black/55"></div>
                {{-- Soft vignette --}}
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/35 to-black/10"></div>
            @else
                <div class="absolute inset-0 bg-background-secondary/60"></div>
            @endif

            {{-- Content --}}
            <div class="relative p-7 sm:p-10">
                <div class="grid gap-8 md:grid-cols-2 md:items-center">
                    {{-- Left side: headline + description --}}
                    <div class="min-w-0">
                        {{-- Stock --}}
                        <div class="mb-4">
                            @if ($product->stock === 0)
                                <span class="inline-flex items-center rounded-full bg-red-500/15 px-3 py-1 text-xs font-semibold text-red-200 border border-red-400/20">
                                    Out of stock
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-green-500/15 px-3 py-1 text-xs font-semibold text-green-200 border border-green-400/20">
                                    In stock
                                </span>
                            @endif
                        </div>

                        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-white">
                            {{ $product->name }}
                        </h1>

                        <div class="mt-3">
                            <p class="text-xs uppercase tracking-wide text-white/60">Starting at</p>
                            <p class="text-2xl font-semibold text-white">
                                {{ $product->price()->formatted->price }}
                            </p>
                        </div>

                        @if(!empty($product->description))
                            <div class="mt-6 prose prose-invert text-white/85 max-w-none">
                                {!! $product->description !!}
                            </div>
                        @endif
                    </div>

                    {{-- Right side: action panel --}}
                    <div class="md:justify-self-end w-full">
                        <div class="rounded-3xl border border-white/10 bg-black/35 backdrop-blur p-6 sm:p-7">
                            <h3 class="text-sm font-semibold text-white/90">
                                Ready to deploy?
                            </h3>
                            <p class="mt-2 text-sm text-white/70">
                                Checkout takes seconds. You can upgrade or cancel anytime.
                            </p>

                            <div class="mt-6 flex items-center gap-3">
                                @if ($product->stock !== 0 && $product->price()->available)
                                    <a
                                        href="{{ route('products.checkout', ['category' => $category, 'product' => $product->slug]) }}"
                                        wire:navigate
                                        class="inline-flex flex-1 items-center justify-center rounded-full
                                               bg-white px-6 py-3 text-sm font-semibold text-black
                                               transition hover:bg-white/90"
                                    >
                                        Add to cart
                                    </a>

                                    <a
                                        href="{{ route('products.checkout', ['category' => $category, 'product' => $product->slug]) }}"
                                        wire:navigate
                                        class="inline-flex h-11 w-11 items-center justify-center rounded-full
                                               bg-black/40 border border-white/20
                                               transition hover:bg-black/55"
                                        aria-label="Add to cart"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="size-5 text-white">
                                            <path d="M7.00488 7.99966V5.99966C7.00488 3.23824 9.24346 0.999664 12.0049 0.999664C14.7663 0.999664 17.0049 3.23824 17.0049 5.99966V7.99966H20.0049C20.5572 7.99966 21.0049 8.44738 21.0049 8.99966V20.9997C21.0049 21.5519 20.5572 21.9997 20.0049 21.9997H4.00488C3.4526 21.9997 3.00488 21.5519 3.00488 20.9997V8.99966C3.00488 8.44738 3.4526 7.99966 4.00488 7.99966H7.00488ZM7.00488 9.99966H5.00488V19.9997H19.0049V9.99966H17.0049V11.9997H15.0049V9.99966H9.00488V11.9997H7.00488V9.99966ZM9.00488 7.99966H15.0049V5.99966C15.0049 4.34281 13.6617 2.99966 12.0049 2.99966C10.348 2.99966 9.00488 4.34281 9.00488 5.99966V7.99966Z"/>
                                        </svg>
                                    </a>
                                @else
                                    <span class="text-sm text-white/70">
                                        This product isn't available right now.
                                    </span>
                                @endif
                            </div>

                            {{-- Small helper line --}}
                            <div class="mt-4 text-xs text-white/55">
                                You'll review billing & configuration on the next step.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>