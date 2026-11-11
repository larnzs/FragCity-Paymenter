@php
    $productPrice = $product->price();
    $isOutOfStock = $product->stock === 0;
    $canOrder = !$isOutOfStock && $productPrice->available;
@endphp

<div class="container fc-product-detail" dir="rtl">
    <nav class="fc-store-breadcrumbs" aria-label="مسیر صفحه">
        <a href="{{ route('home') }}">فروشگاه</a><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        <a href="{{ route('category.show', ['category' => $category->slug]) }}" wire:navigate>{{ $category->name }}</a>
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span aria-current="page">{{ $product->name }}</span>
    </nav>

    <section class="fc-product-overview">
        <div class="fc-product-gallery">
            @if ($product->image)
                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="fc-product-gallery__image">
            @else
                <div class="fc-product-gallery__placeholder" aria-hidden="true">
                    <span class="fc-product-gallery__placeholder-ring"></span>
                    <span class="fc-product-gallery__placeholder-mark">FC<span>.</span></span>
                    <i class="fa-solid fa-server"></i><small>COUNTER-STRIKE SERVER</small>
                </div>
            @endif
            <span class="fc-product-gallery__caption">FRAGCITY / GAME SERVER</span>
        </div>

        <div class="fc-product-summary">
            <div class="fc-product-summary__topline">
                <span class="fc-store-kicker">{{ $category->name }}</span>
                @if ($isOutOfStock)
                    <span class="fc-product-status fc-product-status--out"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> ناموجود</span>
                @elseif ($product->stock > 0)
                    <span class="fc-product-status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> آماده سفارش</span>
                @else
                    <span class="fc-product-status"><i class="fa-solid fa-infinity" aria-hidden="true"></i> قابل سفارش</span>
                @endif
            </div>

            <h1>{{ $product->name }}</h1>
            <p class="fc-product-summary__lead">پلن مناسب را انتخاب کن، منابع و تنظیمات دلخواهت را مشخص کن و قبل از پرداخت، جزئیات و مبلغ سفارش را بررسی کن.</p>

            <div class="fc-product-summary__price-panel">
                <div><span>قیمت پایه محصول</span><strong>{{ $productPrice->available ? $productPrice->formatted->price : 'در صفحه سفارش مشخص می‌شود' }}</strong></div>
                <small>مبلغ نهایی با توجه به پلن و Config Optionهای انتخابی محاسبه می‌شود.</small>
            </div>

            <div class="fc-product-summary__highlights">
                <span><i class="fa-solid fa-sliders" aria-hidden="true"></i> تنظیم منابع و ظرفیت</span>
                <span><i class="fa-solid fa-receipt" aria-hidden="true"></i> مشاهده مبلغ پیش از پرداخت</span>
                <span><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> مدیریت سرویس از پنل</span>
            </div>

            <div class="fc-product-summary__actions">
                @if ($canOrder)
                    <a href="{{ route('products.checkout', ['category' => $category->slug, 'product' => $product->slug]) }}" wire:navigate class="fc-product-buy-button">
                        <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> انتخاب پلن و پیکربندی سرور
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    </a>
                @else
                    <span class="fc-product-buy-button fc-product-buy-button--disabled">
                        <i class="fa-solid fa-ban" aria-hidden="true"></i> {{ $isOutOfStock ? 'این محصول فعلاً موجود نیست' : 'قیمت این محصول در دسترس نیست' }}
                    </span>
                @endif
                <a href="{{ route('category.show', ['category' => $category->slug]) }}" wire:navigate class="fc-product-back-link">بازگشت به محصولات</a>
            </div>
        </div>
    </section>

    @if ($product->description)
        <section class="fc-product-description-section" aria-labelledby="fcProductDescriptionTitle">
            <div class="fc-product-description-section__heading">
                <div><span class="fc-store-kicker">PRODUCT DETAILS</span><h2 id="fcProductDescriptionTitle">معرفی و مشخصات سرویس</h2></div>
                <span class="fc-product-description-section__mark">FC / INFO</span>
            </div>
            <article class="fc-rich-description fc-product-description prose dark:prose-invert">{!! $product->description !!}</article>
        </section>
    @endif
</div>
