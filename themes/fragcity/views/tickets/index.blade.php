<div class="container mt-14 fc-portal-page fc-tickets-page" dir="rtl">
    <x-navigation.breadcrumb />
    <div class="fc-portal-heading">
        <div>
            <span class="fc-portal-kicker">FRAGCITY / SUPPORT CENTER</span>
            <h1>{{ __('ticket.tickets') }}</h1>
            <p>درخواست‌های پشتیبانی خود را ثبت کن و پاسخ تیم FragCity را دنبال کن.</p>
        </div>
        <a href="{{ route('tickets.create') }}" wire:navigate class="fc-portal-heading__action"><i class="fa-solid fa-plus"></i> {{ __('ticket.create_ticket') }}</a>
    </div>

    <div class="fc-portal-list">
        @forelse ($tickets as $ticket)
        <a href="{{ route('tickets.show', $ticket) }}" wire:navigate class="fc-portal-list-card fc-ticket-card">
            <span class="fc-portal-list-card__icon"><x-ri-customer-service-line class="size-6" /></span>
            <span class="fc-portal-list-card__body">
                <strong>#{{ $ticket->id }} — {{ $ticket->subject }}</strong>
                <small>{{ __('ticket.last_activity') }} {{ $ticket->messages()->orderBy('created_at', 'desc')->first()?->created_at->diffForHumans() }}</small>
                @if($ticket->department)<small>{{ $ticket->department }}</small>@endif
            </span>
            <span class="fc-portal-status fc-portal-status--{{ $ticket->status }}">{{ __('ticket.statuses.' . $ticket->status) }}</span>
            <span class="fc-portal-list-card__arrow"><i class="fa-solid fa-arrow-left"></i></span>
        </a>
        @empty
        <div class="fc-portal-empty">
            <span><x-ri-customer-service-line class="size-8" /></span>
            <strong>{{ __('ticket.no_tickets') }}</strong>
            <p>اگر به راهنمایی نیاز داری، یک تیکت پشتیبانی جدید ایجاد کن.</p>
            <a href="{{ route('tickets.create') }}" wire:navigate class="fc-portal-button">ثبت تیکت جدید</a>
        </div>
        @endforelse
    </div>
    <div class="fc-portal-pagination">{{ $tickets->links() }}</div>
</div>
