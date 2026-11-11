@php
    $visibleProductCount = $products->count();
    $availableProductCount = $products->filter(fn ($product) => $product->stock !== 0 && $product->price()->available)->count();
@endphp

<div class="container fc-product-list" dir="rtl">
    <nav class="fc-store-breadcrumbs" aria-label="مسیر صفحه">
        <a href="{{ route('home') }}">فروشگاه</a><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        <span aria-current="page">{{ $category->name }}</span>
    </nav>

    <header class="fc-category-hero">
        <div class="fc-category-hero__copy">
            <span class="fc-store-kicker">FRAGCITY STORE / GAME SERVERS</span>
            <h1>{{ $category->name }}</h1>
            @if ($category->description)
                <article class="fc-rich-description fc-category-hero__description prose dark:prose-invert">{!! $category->description !!}</article>
            @else
                <p class="fc-category-hero__description">پلن‌ها و سرویس‌های این دسته را بررسی کن، محصول مناسب کامیونیتی‌ات را انتخاب کن و سفارش را از فروشگاه FragCity ثبت کن.</p>
            @endif
            <div class="fc-category-hero__facts">
                <span><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> {{ $visibleProductCount }} محصول</span>
                <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ $availableProductCount }} محصول قابل سفارش</span>
            </div>
        </div>
        <div class="fc-category-hero__visual">
            @if ($category->image)
                <img src="{{ Storage::url($category->image) }}" alt="{{ $category->name }}" loading="eager">
            @else
                <div class="fc-category-hero__placeholder" aria-hidden="true">
                    <span class="fc-category-hero__placeholder-ring"></span>
                    <i class="fa-solid fa-crosshairs"></i><strong>FRAGCITY</strong><small>GAME SERVER HOSTING</small>
                </div>
            @endif
            <span class="fc-category-hero__visual-label">CHOOSE YOUR SERVER</span>
        </div>
    </header>

    <div class="fc-product-list__layout">
        <aside class="fc-category-rail" aria-label="دسته‌بندی‌های فروشگاه">
            <div class="fc-category-rail__heading"><span class="fc-store-kicker">BROWSE</span><strong>دسته‌بندی‌ها</strong></div>
            <nav class="fc-category-rail__links">
                @foreach ($categories as $ccategory)
                    <a href="{{ route('category.show', ['category' => $ccategory->slug]) }}" wire:navigate
                       class="{{ $category->id == $ccategory->id ? 'is-active' : '' }}"
                       @if ($category->id == $ccategory->id) aria-current="page" @endif>
                        <span class="fc-category-rail__link-icon"><i class="fa-solid fa-gamepad" aria-hidden="true"></i></span>
                        <span>{{ $ccategory->name }}</span><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </a>
                @endforeach
            </nav>
            <div class="fc-category-rail__help">
                <i class="fa-solid fa-headset" aria-hidden="true"></i>
                <strong>برای انتخاب سرویس راهنمایی می‌خوای؟</strong>
                <p>تیم پشتیبانی FragCity برای انتخاب پلن و منابع مناسب همراهته.</p>
                <a href="https://fragcity.ir/support/">ارتباط با پشتیبانی <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
            </div>
        </aside>

        <main class="fc-product-list__main">
            @if (count($childCategories) >= 1)
                <section class="fc-category-subcategories" aria-labelledby="fcSubcategoriesTitle">
                    <div class="fc-product-section-heading">
                        <div><span class="fc-store-kicker">SUB CATEGORIES</span><h2 id="fcSubcategoriesTitle">دسته‌بندی‌های این بخش</h2></div>
                    </div>
                    <div class="fc-subcategory-grid">
                        @foreach ($childCategories as $childCategory)
                            <a href="{{ route('category.show', ['category' => $childCategory->slug]) }}" wire:navigate class="fc-subcategory-card">
                                <span class="fc-subcategory-card__media">
                                    @if ($childCategory->image)
                                        <img src="{{ Storage::url($childCategory->image) }}" alt="{{ $childCategory->name }}" loading="lazy">
                                    @else
                                        <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                                    @endif
                                </span>
                                <span class="fc-subcategory-card__content">
                                    <strong>{{ $childCategory->name }}</strong>
                                    @if (theme('show_category_description', true) && $childCategory->description)
                                        <span>{{ \Illuminate\Support\Str::limit(strip_tags($childCategory->description), 105) }}</span>
                                    @else
                                        <span>مشاهده محصولات و سرویس‌ها</span>
                                    @endif
                                    <small>مشاهده دسته <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></small>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="fc-category-products" aria-labelledby="fcCategoryProductsTitle">
                <div class="fc-product-section-heading">
                    <div>
                        <span class="fc-store-kicker">AVAILABLE PRODUCTS</span>
                        <h2 id="fcCategoryProductsTitle">محصولات قابل سفارش</h2>
                        <p>یک محصول را انتخاب کن تا پلن‌ها، تنظیمات و هزینهٔ آن را ببینی.</p>
                    </div>
                    <span class="fc-product-count">{{ $visibleProductCount }} محصول</span>
                </div>

                @if ($products->count() > 0)
                    <div class="fc-category-product-grid">
                        @foreach ($products as $product)
                            @php
                                $productPrice = $product->price();
                                $isOutOfStock = $product->stock === 0;
                                $canDirectCheckout = !$isOutOfStock && $productPrice->available && theme('direct_checkout', false);
                            @endphp
                            <article class="fc-catalog-card">
                                <a href="{{ route('products.show', ['category' => $product->category, 'product' => $product->slug]) }}"
                                   wire:navigate class="fc-catalog-card__media" aria-label="مشاهده {{ $product->name }}">
                                    @if ($product->image)
                                        <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" loading="lazy">
                                    @else
                                        <span class="fc-catalog-card__placeholder" aria-hidden="true">
                                            <span class="fc-catalog-card__placeholder-mark">FC<span>.</span></span>
                                            <i class="fa-solid fa-server"></i><small>GAME SERVER</small>
                                        </span>
                                    @endif
                                    @if ($isOutOfStock)
                                        <span class="fc-catalog-card__status fc-catalog-card__status--out">ناموجود</span>
                                    @elseif ($product->stock > 0)
                                        <span class="fc-catalog-card__status"><i aria-hidden="true"></i> آماده سفارش</span>
                                    @endif
                                </a>

                                <div class="fc-catalog-card__body">
                                    <div class="fc-catalog-card__eyebrow"><span>{{ $product->category->name }}</span><span>FRAGCITY / CS</span></div>
                                    <h3><a href="{{ route('products.show', ['category' => $product->category, 'product' => $product->slug]) }}" wire:navigate>{{ $product->name }}</a></h3>
                                    @if (theme('direct_checkout', false) && $product->description)
                                        <p class="fc-catalog-card__description">{{ \Illuminate\Support\Str::limit(strip_tags($product->description), 125) }}</p>
                                    @else
                                        <p class="fc-catalog-card__description">مشخصات، منابع و پلن‌های قابل انتخاب این سرویس را بررسی کن.</p>
                                    @endif

                                    <div class="fc-catalog-card__price">
                                        <span>قیمت پایه</span>
                                        <strong>{{ $productPrice->available ? $productPrice->formatted->price : 'در صفحه سفارش' }}</strong>
                                    </div>

                                    <div class="fc-catalog-card__actions">
                                        @if ($canDirectCheckout)
                                            <a href="{{ route('products.checkout', ['category' => $product->category, 'product' => $product->slug]) }}" wire:navigate class="fc-catalog-card__button fc-catalog-card__button--primary">
                                                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> افزودن به سفارش
                                            </a>
                                            <a href="{{ route('products.show', ['category' => $product->category, 'product' => $product->slug]) }}" wire:navigate class="fc-catalog-card__button fc-catalog-card__button--icon" aria-label="جزئیات {{ $product->name }}">
                                                <i class="fa-solid fa-arrow-up-left-from-square" aria-hidden="true"></i>
                                            </a>
                                        @elseif ($isOutOfStock)
                                            <span class="fc-catalog-card__button fc-catalog-card__button--disabled">فعلاً ناموجود</span>
                                            <a href="{{ route('products.show', ['category' => $product->category, 'product' => $product->slug]) }}" wire:navigate class="fc-catalog-card__button fc-catalog-card__button--icon" aria-label="جزئیات {{ $product->name }}">
                                                <i class="fa-solid fa-arrow-up-left-from-square" aria-hidden="true"></i>
                                            </a>
                                        @else
                                            <a href="{{ route('products.show', ['category' => $product->category, 'product' => $product->slug]) }}" wire:navigate class="fc-catalog-card__button fc-catalog-card__button--primary">
                                                مشاهده مشخصات و پلن‌ها <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="fc-product-empty">
                        <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                        <strong>هنوز محصولی در این دسته قرار نگرفته.</strong>
                        <span>به‌زودی سرویس‌های جدید FragCity به این بخش اضافه می‌شوند.</span>
                    </div>
                @endif
            </section>
        </main>
    </div>
</div>
