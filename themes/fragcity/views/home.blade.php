@php
    $allStoreProducts = $categories
        ->flatMap(fn ($category) => $category->products->filter(fn ($product) => !$product->hidden))
        ->unique('id');

    $storeProducts = $allStoreProducts->take(6);
@endphp

<div class="fc-marketplace" dir="rtl">
    <div class="fc-marketplace__ambient" aria-hidden="true"></div>

    <div class="fc-marketplace__shell">
        <section class="fc-marketplace__hero" aria-labelledby="fcMarketplaceTitle">
            <div class="fc-marketplace__hero-copy">
                <div class="fc-marketplace__eyebrow">
                    <span class="fc-marketplace__eyebrow-mark">FC</span>
                    <span>FRAGCITY STORE</span>
                    <span class="fc-marketplace__eyebrow-divider">/</span>
                    <span>GAME SERVER HOSTING</span>
                </div>

                <h1 id="fcMarketplaceTitle">
                    سرور بازی‌ات رو
                    <span>همین‌جا سفارش بده.</span>
                </h1>

                <p class="fc-marketplace__intro">
                    بازی و سرویس موردنظرت رو انتخاب کن، منابع و تنظیماتش رو مشخص کن
                    و قبل از پرداخت، جزئیات سفارش و هزینه رو داخل فروشگاه ببین.
                </p>

                <div class="fc-marketplace__hero-actions">
                    <a href="#fc-marketplace-categories" class="fc-marketplace__button fc-marketplace__button--primary">
                        انتخاب بازی و سرویس
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    </a>
                    <a href="#fc-marketplace-products" class="fc-marketplace__button fc-marketplace__button--secondary">
                        دیدن محصولات
                    </a>
                </div>

                <div class="fc-marketplace__hero-notes" aria-label="ویژگی‌های خرید">
                    <span><i class="fa-solid fa-sliders" aria-hidden="true"></i> انتخاب منابع و تنظیمات</span>
                    <span><i class="fa-solid fa-receipt" aria-hidden="true"></i> مشاهده هزینه پیش از پرداخت</span>
                </div>
            </div>

            <aside class="fc-marketplace__overview" aria-label="خلاصه فروشگاه">
                <div class="fc-marketplace__overview-top">
                    <span class="fc-marketplace__overview-icon"><i class="fa-solid fa-store" aria-hidden="true"></i></span>
                    <span>
                        <small>FRAGCITY / STORE</small>
                        <strong>همه‌چیز برای شروع سفارش</strong>
                    </span>
                    <span class="fc-marketplace__overview-live"><i aria-hidden="true"></i> ONLINE</span>
                </div>

                <div class="fc-marketplace__overview-stats">
                    <div>
                        <strong>{{ $categories->count() }}</strong>
                        <span>دسته‌بندی فروشگاه</span>
                    </div>
                    <div>
                        <strong>{{ $allStoreProducts->count() }}</strong>
                        <span>محصول قابل مشاهده</span>
                    </div>
                </div>

                <div class="fc-marketplace__overview-footer">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>پلن، گزینه‌های قابل انتخاب و مبلغ نهایی در صفحه سفارش مشخص می‌شوند.</span>
                </div>
            </aside>
        </section>

        <section class="fc-marketplace__section" id="fc-marketplace-categories" aria-labelledby="fcMarketplaceCategoriesTitle">
            <div class="fc-marketplace__section-heading">
                <div>
                    <span class="fc-marketplace__kicker">01 / CATEGORIES</span>
                    <h2 id="fcMarketplaceCategoriesTitle">برای کدوم بازی سرور می‌خوای؟</h2>
                    <p>اول دسته‌بندی رو انتخاب کن؛ بعد محصولات و پلن‌های موجود رو ببین.</p>
                </div>
                <span class="fc-marketplace__section-count">{{ $categories->count() }} دسته</span>
            </div>

            @if ($categories->isNotEmpty())
                <div class="fc-marketplace__category-grid">
                    @foreach ($categories as $category)
                        @php
                            $categoryProducts = $category->products->filter(fn ($product) => !$product->hidden);
                        @endphp

                        <a
                            href="{{ route('category.show', ['category' => $category->slug]) }}"
                            wire:navigate
                            class="fc-marketplace__category-card"
                        >
                            <span class="fc-marketplace__category-media">
                                @if ($category->image)
                                    <img src="{{ Storage::url($category->image) }}" alt="{{ $category->name }}" loading="lazy">
                                @else
                                    <span class="fc-marketplace__category-placeholder" aria-hidden="true">
                                        <i class="fa-solid fa-gamepad"></i>
                                    </span>
                                @endif
                                <span class="fc-marketplace__category-index">CATEGORY / {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            </span>

                            <span class="fc-marketplace__category-info">
                                <span>
                                    <strong>{{ $category->name }}</strong>
                                    <small>{{ $categoryProducts->count() }} محصول قابل مشاهده</small>
                                </span>
                                <span class="fc-marketplace__arrow" aria-hidden="true"><i class="fa-solid fa-arrow-left"></i></span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="fc-marketplace__empty">
                    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                    <strong>فعلاً دسته‌بندی‌ای برای نمایش وجود نداره.</strong>
                    <span>بعد از اضافه‌شدن محصولات، از همین‌جا می‌تونی سفارش رو شروع کنی.</span>
                </div>
            @endif
        </section>

        <section class="fc-marketplace__section" id="fc-marketplace-products" aria-labelledby="fcMarketplaceProductsTitle">
            <div class="fc-marketplace__section-heading">
                <div>
                    <span class="fc-marketplace__kicker">02 / PRODUCTS</span>
                    <h2 id="fcMarketplaceProductsTitle">محصولات فروشگاه</h2>
                    <p>چند محصول از دسته‌بندی‌های فعال؛ برای دیدن جزئیات و تمام گزینه‌ها وارد صفحه محصول شو.</p>
                </div>
                <span class="fc-marketplace__section-count">{{ $allStoreProducts->count() }} محصول</span>
            </div>

            @if ($storeProducts->isNotEmpty())
                <div class="fc-marketplace__product-grid">
                    @foreach ($storeProducts as $product)
                        @php
                            $productPrice = $product->price();
                        @endphp

                        <article class="fc-marketplace__product-card">
                            <a
                                href="{{ route('products.show', ['category' => $product->category->slug, 'product' => $product->slug]) }}"
                                wire:navigate
                                class="fc-marketplace__product-media"
                                aria-label="مشاهده {{ $product->name }}"
                            >
                                @if ($product->image)
                                    <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" loading="lazy">
                                @else
                                    <span class="fc-marketplace__product-placeholder" aria-hidden="true">
                                        <span class="fc-marketplace__product-placeholder-mark">FC<span>.</span></span>
                                        <small>GAME SERVER</small>
                                    </span>
                                @endif
                                @if ($product->stock === 0)
                                    <span class="fc-marketplace__stock-badge">ناموجود</span>
                                @endif
                            </a>

                            <div class="fc-marketplace__product-body">
                                <div class="fc-marketplace__product-meta">
                                    <span>{{ $product->category->name }}</span>
                                    <span>FRAGCITY SERVICE</span>
                                </div>

                                <h3>{{ $product->name }}</h3>

                                <div class="fc-marketplace__product-price">
                                    <span>{{ $productPrice->available ? 'شروع قیمت' : 'وضعیت قیمت' }}</span>
                                    <strong>{{ $productPrice->available ? $productPrice->formatted->price : 'در صفحه سفارش' }}</strong>
                                </div>

                                <a
                                    href="{{ route('products.show', ['category' => $product->category->slug, 'product' => $product->slug]) }}"
                                    wire:navigate
                                    class="fc-marketplace__product-cta"
                                >
                                    مشاهده جزئیات و پلن‌ها
                                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="fc-marketplace__empty">
                    <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                    <strong>هنوز محصولی برای سفارش نمایش داده نمی‌شه.</strong>
                    <span>از بخش دسته‌بندی‌ها شروع کن یا بعداً دوباره به فروشگاه سر بزن.</span>
                </div>
            @endif
        </section>

        <section class="fc-marketplace__benefits" aria-labelledby="fcMarketplaceBenefitsTitle">
            <div class="fc-marketplace__section-heading">
                <div>
                    <span class="fc-marketplace__kicker">03 / WHY FRAGCITY</span>
                    <h2 id="fcMarketplaceBenefitsTitle">خرید سرور، بدون حدس‌زدن هزینه</h2>
                </div>
            </div>

            <div class="fc-marketplace__benefit-grid">
                <article class="fc-marketplace__benefit-card">
                    <span><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
                    <div>
                        <h3>منابع متناسب با نیازت</h3>
                        <p>گزینه‌های قابل انتخاب هر محصول رو در همان صفحه سفارش تنظیم کن.</p>
                    </div>
                </article>
                <article class="fc-marketplace__benefit-card">
                    <span><i class="fa-solid fa-calculator" aria-hidden="true"></i></span>
                    <div>
                        <h3>قیمت شفاف</h3>
                        <p>مبلغ پلن و گزینه‌های انتخابی قبل از تأیید سفارش محاسبه می‌شه.</p>
                    </div>
                </article>
                <article class="fc-marketplace__benefit-card">
                    <span><i class="fa-solid fa-gauge-high" aria-hidden="true"></i></span>
                    <div>
                        <h3>مدیریت یکپارچه</h3>
                        <p>سفارش‌ها، فاکتورها و سرویس‌ها از حساب کاربری Paymenter قابل پیگیری‌اند.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="fc-marketplace__steps" aria-labelledby="fcMarketplaceStepsTitle">
            <div class="fc-marketplace__steps-heading">
                <span class="fc-marketplace__kicker">04 / HOW TO ORDER</span>
                <h2 id="fcMarketplaceStepsTitle">سفارش در سه قدم</h2>
                <p>از انتخاب سرویس تا پیگیری سفارش، مسیر خرید داخل همین فروشگاه انجام می‌شه.</p>
            </div>

            <div class="fc-marketplace__steps-grid">
                <article class="fc-marketplace__step">
                    <span class="fc-marketplace__step-number">01</span>
                    <div>
                        <h3>انتخاب بازی و محصول</h3>
                        <p>دسته‌بندی مناسب رو باز کن و محصول موردنظرت رو انتخاب کن.</p>
                    </div>
                </article>
                <article class="fc-marketplace__step">
                    <span class="fc-marketplace__step-number">02</span>
                    <div>
                        <h3>تنظیم پلن و منابع</h3>
                        <p>مدت سرویس و گزینه‌های قابل انتخاب رو مشخص کن و مبلغ رو بررسی کن.</p>
                    </div>
                </article>
                <article class="fc-marketplace__step">
                    <span class="fc-marketplace__step-number">03</span>
                    <div>
                        <h3>پرداخت و پیگیری</h3>
                        <p>سفارش رو ثبت کن و وضعیت فاکتور و سرویس رو از حساب کاربری دنبال کن.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="fc-marketplace__closing">
            <div>
                <span class="fc-marketplace__kicker">READY WHEN YOU ARE</span>
                <h2>اولین قدم رو بردار؛ سرورت منتظرته.</h2>
                <p>دسته‌بندی‌ها رو ببین و محصول مناسب کامیونیتی‌ات رو انتخاب کن.</p>
            </div>
            <a href="#fc-marketplace-categories" class="fc-marketplace__button fc-marketplace__button--primary">
                رفتن به دسته‌بندی‌ها
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            </a>
        </section>
    </div>

    {!! hook('pages.home') !!}
</div>
