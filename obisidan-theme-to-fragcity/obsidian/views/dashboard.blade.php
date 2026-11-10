{{-- themes/obsidian/views/dashboard.blade.php --}}

<div class="w-full px-6 lg:px-10 2xl:px-12 mt-10 pb-24">
    <div class="max-w-[1650px] mx-auto">
        {{-- Header --}}
        <div class="mb-10">
            <p class="text-xs uppercase tracking-widest text-base/50">Welcome back</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-base">Dashboard</h1>
            <p class="mt-2 text-sm text-base/60">
                Manage your active services, invoices, and support tickets.
            </p>
        </div>

        @php
            $activeServicesCount = Auth::user()->services()->where('status', 'active')->count();
            $unpaidInvoicesCount = Auth::user()->invoices()->where('status', 'pending')->count();
            $openTicketsCount = config('settings.tickets_disabled', false)
                ? null
                : Auth::user()->tickets()->where('status', '!=', 'closed')->count();
        @endphp

        {{-- Stat cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-10">

            {{-- Active Services --}}
            <div class="relative h-[200px] rounded-3xl border border-neutral overflow-hidden bg-purple-500 ring-1 ring-white/10 shadow-[0_0_0_1px_rgba(255,255,255,0.06)]">
                <div class="absolute inset-0 bg-black/20"></div>
                <div class="absolute inset-0 opacity-35 bg-gradient-to-br from-white/10 via-transparent to-black/10"></div>

                <div class="relative z-10 p-6">
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-black/20">
                        <x-ri-archive-stack-fill class="size-5 text-white" />
                    </div>

                    <div class="mt-6">
                        <div class="text-3xl font-semibold text-white leading-none">{{ $activeServicesCount }}</div>
                        <div class="mt-2 text-xs text-white/75">Active Services</div>
                    </div>
                </div>
            </div>

            {{-- Unpaid Invoices --}}
            <div class="relative h-[200px] rounded-3xl border border-neutral overflow-hidden bg-amber-500 ring-1 ring-white/10 shadow-[0_0_0_1px_rgba(255,255,255,0.06)]">
                <div class="absolute inset-0 bg-black/20"></div>
                <div class="absolute inset-0 opacity-35 bg-gradient-to-br from-white/10 via-transparent to-black/10"></div>

                <div class="relative z-10 p-6">
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-black/20">
                        <x-ri-receipt-fill class="size-5 text-white" />
                    </div>

                    <div class="mt-6">
                        <div class="text-3xl font-semibold text-white leading-none">{{ $unpaidInvoicesCount }}</div>
                        <div class="mt-2 text-xs text-white/75">Unpaid Invoices</div>
                    </div>
                </div>
            </div>

            {{-- Open Tickets --}}
            <div class="relative h-[200px] rounded-3xl border border-neutral overflow-hidden bg-sky-600 ring-1 ring-white/10 shadow-[0_0_0_1px_rgba(255,255,255,0.06)]">
                <div class="absolute inset-0 bg-black/20"></div>
                <div class="absolute inset-0 opacity-35 bg-gradient-to-br from-white/10 via-transparent to-black/10"></div>

                <div class="relative z-10 p-6">
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-black/20">
                        <x-ri-customer-service-fill class="size-5 text-white" />
                    </div>

                    <div class="mt-6">
                        <div class="text-3xl font-semibold text-white leading-none">{{ $openTicketsCount }}</div>
                        <div class="mt-2 text-xs text-white/75">Open Tickets</div>
                    </div>
                </div>
            </div>

            {{-- Account Status --}}
            <div class="relative h-[200px] rounded-3xl border border-neutral overflow-hidden bg-emerald-600 ring-1 ring-white/10 shadow-[0_0_0_1px_rgba(255,255,255,0.06)]">
                <div class="absolute inset-0 bg-black/20"></div>
                <div class="absolute inset-0 opacity-35 bg-gradient-to-br from-white/10 via-transparent to-black/10"></div>

                <div class="relative z-10 p-6">
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-black/20">
                        <x-ri-pulse-fill class="size-5 text-white" />
                    </div>

                    <div class="mt-6">
                        <div class="text-3xl font-semibold text-white leading-none">Active</div>
                        <div class="mt-2 text-xs text-white/75">Account Status</div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Active Services --}}
        <div class="mb-12">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-lg font-semibold text-base">Active Services</h2>
                <a href="{{ route('services') }}" wire:navigate
                   class="text-sm text-base/60 hover:text-base inline-flex items-center gap-2">
                    View all <x-ri-arrow-right-line class="size-4" />
                </a>
            </div>

            <livewire:services.widget status="active" />
        </div>

        {{-- Bottom row --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 items-start">
            <div>
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-semibold text-base">Recent Invoices</h2>
                    <a href="{{ route('invoices') }}" wire:navigate
                       class="text-sm text-base/60 hover:text-base inline-flex items-center gap-2">
                        View all <x-ri-arrow-right-line class="size-4" />
                    </a>
                </div>

                <livewire:invoices.widget :limit="3" />
            </div>

            <div>
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-semibold text-base">Support Tickets</h2>

                    @if (!config('settings.tickets_disabled', false))
                        <a href="{{ route('tickets.create') }}" wire:navigate
                           class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold bg-primary text-white hover:opacity-90 transition">
                            <x-ri-add-line class="size-4" />
                            New ticket
                        </a>
                    @endif
                </div>

                <livewire:tickets.widget />
            </div>
        </div>

        <div class="mt-10">
            {!! hook('pages.dashboard') !!}
        </div>
    </div>
</div>
