@php
    $storeProducts = $categories
        ->flatMap(fn ($category) => $category->products->filter(fn ($product) => !$product->hidden))
        ->unique('id')
        ->take(6);
@endphp

<div class="fc-store-home" dir="rtl">
    <div class="fc-store-home__background-grid" aria-hidden="true"></div>
    <div class="fc-store-home__glow fc-store-home__glow--one" aria-hidden="true"></div>
    <div class="fc-store-home__glow fc-store-home__glow--two" aria-hidden="true"></div>

    <div class="fc-store-home__shell">
        <section class="fc-store-home__hero" aria-labelledby="fcStoreHeroTitle">
            <div class="fc-store-home__copy">
                <div class="fc-store-home__notice" data-fc-reveal="up">
                    <span class="fc-store-home__notice-mark">FC</span>
                    <span>
                        <strong>فروشگاه رسمی FragCity</strong>
                        <small>GAME SERVER HOSTING · BUILT FOR COMMUNITIES</small>
                    </span>
                    <span class="fc-store-home__notice-index">STORE / 01</span>
                </div>

                <div class="fc-store-home__eyebrow" data-fc-reveal="up" data-fc-reveal-delay="50">
                    <span class="fc-store-home__eyebrow-icon" aria-hidden="true">✦</span>
                    <span>FRAGCITY</span>
                    <span class="fc-store-home__eyebrow-divider">/</span>
                    <span>YOUR GAME SPACE STARTS HERE</span>
                </div>

                <h1 id="fcStoreHeroTitle" data-fc-reveal="up" data-fc-reveal-delay="100">
                    سرور بازی‌ات را انتخاب کن؛
                    <span>کامیونیتی‌ات را بساز.</span>
                </h1>

                <p class="fc-store-home__description" data-fc-reveal="up" data-fc-reveal-delay="150">
                    از سرور اختصاصی بازی تا منابع و تنظیمات موردنیاز کامیونیتی‌ات؛
                    سرویس مناسب را انتخاب کن، جزئیات و هزینه را شفاف ببین و سفارشت را از همین‌جا ثبت کن.
                </p>

                <div class="fc-store-home__actions" data-fc-reveal="up" data-fc-reveal-delay="200">
                    <a href="#fc-store-categories" class="fc-store-home__button fc-store-home__button--primary">
                        <span>مشاهده سرویس‌ها</span>
                        <span aria-hidden="true">←</span>
                    </a>
                    <a href="{{ route('login') }}" class="fc-store-home__button fc-store-home__button--secondary">
                        <span>ورود به حساب کاربری</span>
                    </a>
                </div>

                <div class="fc-store-home__signals" data-fc-reveal="up" data-fc-reveal-delay="250">
                    <span><i aria-hidden="true"></i> انتخاب پلن و منابع</span>
                    <span><i aria-hidden="true"></i> مشاهده هزینه پیش از سفارش</span>
                    <span><i aria-hidden="true"></i> مدیریت سفارش در یک پنل</span>
                </div>
            </div>

            <div class="fc-store-home__visual" data-fc-reveal="left" aria-label="پیش‌نمایش تنظیمات سرور FragCity">
                <div class="fc-store-home__visual-halo" aria-hidden="true"></div>
                <div class="fc-store-home__visual-orbit fc-store-home__visual-orbit--one" aria-hidden="true"></div>
                <div class="fc-store-home__visual-orbit fc-store-home__visual-orbit--two" aria-hidden="true"></div>

                <div class="fc-store-home__visual-topline">
                    <span class="fc-store-home__visual-brand">
                        <span class="fc-store-home__visual-brand-mark">FC<span>.</span></span>
                        <span>
                            <strong>FRAGCITY</strong>
                            <small>SERVER CONFIGURATION</small>
                        </span>
                    </span>
                    <span class="fc-store-home__visual-live"><i aria-hidden="true"></i> READY TO BUILD</span>
                </div>

                <div class="fc-store-home__visual-panel fc-glass-surface">
                    <div class="fc-store-home__panel-heading">
                        <span class="fc-store-home__panel-icon" aria-hidden="true">⌘</span>
                        <span>
                            <small>YOUR NEXT GAME SERVER</small>
                            <strong>فضای بازی، به سبک تو</strong>
                        </span>
                        <span class="fc-store-home__panel-dots" aria-hidden="true">•••</span>
                    </div>

                    <div class="fc-store-home__server-preview">
                        <div class="fc-store-home__server-emblem" aria-hidden="true">
                            <span class="fc-store-home__server-emblem-ring"></span>
                            <strong>FC</strong>
                            <i></i>
                        </div>
                        <div class="fc-store-home__server-copy">
                            <span class="fc-store-home__mini-label">MAKE IT YOURS</span>
                            <strong>سرور اختصاصی کامیونیتی تو</strong>
                            <small>بازی، منابع و تنظیمات را خودت انتخاب کن.</small>
                        </div>
                    </div>

                    <div class="fc-store-home__config-grid">
                        <div class="fc-store-home__config-item">
                            <span>GAME</span>
                            <strong>CS2 / CS:GO</strong>
                        </div>
                        <div class="fc-store-home__config-item">
                            <span>MEMORY</span>
                            <strong>قابل انتخاب</strong>
                        </div>
                        <div class="fc-store-home__config-item">
                            <span>TICKRATE</span>
                            <strong>64 / 128</strong>
                        </div>
                        <div class="fc-store-home__config-item">
                            <span>CONTROL</span>
                            <strong>از پنل تو</strong>
                        </div>
                    </div>

                    <div class="fc-store-home__panel-foot">
                        <span><i aria-hidden="true"></i> تنظیمات متناسب با نیاز تو</span>
                        <span>FRAGCITY / 2026</span>
                    </div>
                </div>

                <div class="fc-store-home__visual-float fc-store-home__visual-float--top fc-glass-surface">
                    <span class="fc-store-home__float-icon">↗</span>
                    <span><strong>CHOOSE</strong><small>YOUR PLAN</small></span>
                </div>
                <div class="fc-store-home__visual-float fc-store-home__visual-float--bottom fc-glass-surface">
                    <span class="fc-store-home__float-icon">⌘</span>
                    <span><strong>BUILD</strong><small>YOUR COMMUNITY</small></span>
                </div>
            </div>
        </section>

        <div class="fc-store-home__scroll-cue" aria-hidden="true">
            <span></span>
            <small>EXPLORE THE STORE</small>
        </div>

        <section class="fc-store-home__benefits" aria-label="مزیت‌های خرید از FragCity">
            <article class="fc-store-home__benefit" data-fc-reveal="up">
                <span class="fc-store-home__benefit-icon" aria-hidden="true">01</span>
                <div>
                    <strong>انتخاب دقیق منابع</strong>
                    <small>پلن و مشخصات متناسب با نیازت</small>
                </div>
            </article>
            <article class="fc-store-home__benefit" data-fc-reveal="up" data-fc-reveal-delay="70">
                <span class="fc-store-home__benefit-icon" aria-hidden="true">02</span>
                <div>
                    <strong>هزینه شفاف</strong>
                    <small>جزئیات مبلغ پیش از ثبت سفارش</small>
                </div>
            </article>
            <article class="fc-store-home__benefit" data-fc-reveal="up" data-fc-reveal-delay="140">
                <span class="fc-store-home__benefit-icon" aria-hidden="true">03</span>
                <div>
                    <strong>مدیریت یکپارچه</strong>
                    <small>سفارش‌ها و سرویس‌ها در پنل تو</small>
                </div>
            </article>
        </section>

        <section class="fc-store-home__section" id="fc-store-categories" aria-labelledby="fcStoreCategoriesTitle">
            <div class="fc-store-home__section-heading" data-fc-reveal="up">
                <span class="fc-store-home__kicker"><span>01</span> / EXPLORE THE STORE</span>
                <h2 id="fcStoreCategoriesTitle">از کجا شروع می‌کنی؟</h2>
                <p>دسته‌بندی سرویس‌ها را بررسی کن و گزینه‌ای را پیدا کن که با بازی و کامیونیتی تو هماهنگ است.</p>
            </div>

            @if ($categories->isNotEmpty())
                <div class="fc-store-home__category-grid">
                    @foreach ($categories as $category)
                        @php
                            $categoryProducts = $category->products->filter(fn ($product) => !$product->hidden);
                        @endphp
                        <article class="fc-store-home__category-card" data-fc-reveal="up" data-fc-reveal-delay="{{ ($loop->index % 4) * 65 }}">
                            <a href="{{ route('category.show', ['category' => $category->slug]) }}" wire:navigate class="fc-store-home__category-link">
                                <div class="fc-store-home__category-art">
                                    @if ($category->image)
                                        <img src="{{ Storage::url($category->image) }}" alt="{{ $category->name }}" loading="lazy">
                                    @else
                                        <span class="fc-store-home__category-art-mark" aria-hidden="true">FC<span>.</span></span>
                                        <span class="fc-store-home__category-art-orbit" aria-hidden="true"></span>
                                    @endif
                                    <span class="fc-store-home__category-art-index">CATEGORY / {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                </div>
                                <div class="fc-store-home__category-content">
                                    <div class="fc-store-home__category-title-row">
                                        <h3>{{ $category->name }}</h3>
                                        @if ($categoryProducts->isNotEmpty())
                                            <span class="fc-store-home__category-count">{{ $categoryProducts->count() }} سرویس</span>
                                        @else
                                            <span class="fc-store-home__category-count">مشاهده دسته</span>
                                        @endif
                                    </div>
                                    @if (theme('show_category_description', true) && $category->description)
                                        <div class="fc-store-home__category-description prose dark:prose-invert">
                                            {!! $category->description !!}
                                        </div>
                                    @else
                                        <p class="fc-store-home__category-description">پلن‌ها و سرویس‌های این بخش را بررسی کن و گزینه مناسب خودت را انتخاب کن.</p>
                                    @endif
                                    <span class="fc-store-home__category-cta">
                                        مشاهده سرویس‌ها
                                        <span aria-hidden="true">←</span>
                                    </span>
                                </div>
                            </a>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="fc-store-home__empty">
                    <strong>هنوز سرویسی برای نمایش وجود ندارد.</strong>
                    <span>به‌زودی محصولات FragCity در این بخش قرار می‌گیرند.</span>
                </div>
            @endif
        </section>

        @if ($storeProducts->isNotEmpty())
            <section class="fc-store-home__section fc-store-home__products" aria-labelledby="fcStoreProductsTitle">
                <div class="fc-store-home__section-heading fc-store-home__section-heading--split" data-fc-reveal="up">
                    <div>
                        <span class="fc-store-home__kicker"><span>02</span> / FEATURED SERVICES</span>
                        <h2 id="fcStoreProductsTitle">سرویس‌های قابل سفارش</h2>
                    </div>
                    <p>چند گزینه از محصولات فعال فروشگاه؛ برای دیدن همه پلن‌ها وارد دسته‌بندی مربوطه شو.</p>
                </div>

                <div class="fc-store-home__product-grid">
                    @foreach ($storeProducts as $product)
                        @php
                            $productPrice = $product->price();
                        @endphp
                        <article class="fc-store-home__product-card" data-fc-reveal="up" data-fc-reveal-delay="{{ ($loop->index % 3) * 65 }}">
                            <a href="{{ route('products.show', ['category' => $product->category->slug, 'product' => $product->slug]) }}" wire:navigate class="fc-store-home__product-image-link">
                                <div class="fc-store-home__product-image">
                                    @if ($product->image)
                                        <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" loading="lazy">
                                    @else
                                        <span class="fc-store-home__product-placeholder" aria-hidden="true">
                                            <span class="fc-store-home__product-placeholder-ring"></span>
                                            <strong>FC<span>.</span></strong>
                                            <small>GAME SERVER</small>
                                        </span>
                                    @endif
                                    <span class="fc-store-home__product-image-label">FRAGCITY / SERVICE</span>
                                </div>
                            </a>
                            <div class="fc-store-home__product-body">
                                <h3>{{ $product->name }}</h3>
                                <div class="fc-store-home__product-price">
                                    @if ($productPrice->available)
                                        <span>شروع قیمت</span>
                                        <strong>{{ $productPrice->formatted->price }}</strong>
                                    @else
                                        <span>قیمت</span>
                                        <strong>در دسترس نیست</strong>
                                    @endif
                                </div>
                                <a href="{{ route('products.show', ['category' => $product->category->slug, 'product' => $product->slug]) }}" wire:navigate class="fc-store-home__product-cta">
                                    مشاهده جزئیات و پلن‌ها
                                    <span aria-hidden="true">←</span>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="fc-store-home__closing" data-fc-reveal="up">
            <div>
                <span class="fc-store-home__kicker"><span>03</span> / MAKE IT YOURS</span>
                <h2>فضای بازی بعدی‌ات را بساز.</h2>
                <p>از یک سرویس شروع کن؛ کامیونیتی خودت را قدم‌به‌قدم توسعه بده.</p>
            </div>
            <a href="#fc-store-categories" class="fc-store-home__button fc-store-home__button--primary">
                <span>شروع انتخاب سرویس</span>
                <span aria-hidden="true">←</span>
            </a>
        </section>
    </div>

    {!! hook('pages.home') !!}
</div>
