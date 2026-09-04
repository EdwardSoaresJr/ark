@props([
    'token',
    'portalAuthorization',
    'staffPreview' => false,
    'payingRemaining' => false,
    'class' => '',
])

@php
    $depositAmountLabel = $portalAuthorization['deposit_amount'] ?? $portalAuthorization['approved_amount'];
@endphp

<section
    id="portal-estimate-deposit"
    @class(['scroll-mt-28 overflow-hidden rounded-xl border-2 border-amber-400 bg-white shadow-sm space-y-0', $class])
>
    <div class="border-b border-amber-200 bg-amber-50 px-4 py-4 sm:px-5">
        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-amber-800">{{ $payingRemaining ? 'Remaining balance' : 'Step 3 — Deposit' }}</p>
        <h2 class="mt-1 text-lg font-bold text-slate-950">{{ $payingRemaining ? 'Remaining' : 'Deposit' }}: {{ $depositAmountLabel }}</h2>
        <p class="mt-1 text-sm leading-6 text-slate-700">
            @if ($payingRemaining)
                This is the leftover balance on work you already approved. It is not approval for any additional repairs.
            @else
                This deposit is for the work you already approved. We use it to schedule that work.
                It is not approval for any additional repairs.
            @endif
        </p>
    </div>

    <div class="space-y-3 px-4 py-4 sm:px-5 text-sm leading-6 text-slate-700">
        @if ($staffPreview)
            <p class="rounded-md border border-amber-200 bg-amber-50/80 px-3 py-3 text-amber-950">
                Online card deposit is not available in Core — record the deposit in ARK or collect at the shop.
            </p>
        @else
            <p class="font-semibold text-slate-950">Pay at the shop</p>
            <p>
                Online card payment is not available on this estimate. Please contact the shop to pay this deposit, or ask staff to record it in ARK.
            </p>
            {{-- Seam: managed online pay belongs to ARK Cloud Payments (future). --}}
        @endif
    </div>
</section>
