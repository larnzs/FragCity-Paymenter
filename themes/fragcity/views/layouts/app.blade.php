<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(in_array(app()->getLocale(), config('app.rtl_locales', []), true) || app()->getLocale() === 'fa') dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Paymenter') }}@isset($title) - {{ $title }}@endisset</title>
    @livewireStyles
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    @vite(['themes/' . config('settings.theme') . '/js/app.js', 'themes/' . config('settings.theme') . '/css/app.css'], config('settings.theme'))
    @include('layouts.customer-colors')
    @if (config('settings.favicon')) <link rel="icon" href="{{ Storage::url(config('settings.favicon')) }}"> @endif
    @isset($title)
    <meta content="{{ config('app.name', 'Paymenter') . ' - ' . $title }}" property="og:title">
    <meta content="{{ config('app.name', 'Paymenter') . ' - ' . $title }}" name="title">
    @endisset
    @isset($description)
    <meta content="{{ $description }}" property="og:description">
    <meta content="{{ $description }}" name="description">
    @endisset
    @isset($image)
    <meta content="{{ $image }}" property="og:image">
    <meta content="{{ $image }}" name="image">
    @endisset
    <meta name="theme-color" content="{{ theme('primary') }}">
    {!! hook('head') !!}
</head>
<body class="w-full bg-background text-base min-h-screen flex flex-col antialiased" x-cloak
    x-data="{ theme: $persist('system').as('theme_mode'), systemDark: window.matchMedia('(prefers-color-scheme: dark)').matches, init() { window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => { this.systemDark = e.matches; }); }, get isDark() { return this.theme === 'dark' || (this.theme === 'system' && this.systemDark); } }"
    :class="{'dark': isDark}">
    {!! hook('body') !!}
    <x-navigation />
    <div class="w-full flex flex-col flex-grow">
        <div class="w-full flex flex-grow min-w-0">
            @if (isset($sidebar) && $sidebar) <x-navigation.sidebar title="$title" /> @endif
            <div class="{{ (isset($sidebar) && $sidebar) ? 'md:ml-64 rtl:ml-0 rtl:md:mr-64' : '' }} flex flex-col flex-grow min-w-0">
                <main class="fc-page-main grow">{{ $slot }}</main>
                <x-notification />
                <x-confirmation />
            </div>
        </div>
        <x-navigation.footer />
    </div>
    <x-impersonating />
    @livewireScriptConfig
    {!! hook('footer') !!}
</body>
</html>
