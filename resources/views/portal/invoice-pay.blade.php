@php
    $shopName = \App\Ark\Operations\Settings\ShopSettings::current()->displayName();
    $payMode = $payMode ?? '';
    $isDeposit = in_array($payMode, ['deposit', 'remaining_deposit'], true)
        || ($amountLabel ?? '') === 'Deposit requested'
        || str_contains(strtolower((string) ($pageTitle ?? '')), 'deposit');
@endphp

<x-portal.app>
    <section class="customer-panel">
        @if ($staffPreview ?? false)
            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <p class="font-semibold">Staff preview</p>
                <p class="mt-1 text-amber-900">This is what the customer sees. Online card pay is not available in Core — record payment in ARK or collect at the shop.</p>
            </div>
        @endif

        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $shopName }}</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ $pageTitle ?? 'Invoice balance' }}</h1>

        <div class="mt-4 space-y-2 text-sm leading-6 text-slate-600">
            <p><span class="font-semibold text-slate-900">{{ $repairOrder->vehicle->display_name }}</span></p>
            <p>Repair order #{{ $repairOrder->repair_order_id }}</p>
            <p class="text-lg font-black text-slate-950">{{ $amountLabel ?? 'Balance due' }}: {{ $balanceDue }}</p>
            @if ($payMode === 'remaining_deposit')
                <p>
                    This is the leftover balance on work you already approved — not extra repairs.
                </p>
            @elseif ($isDeposit)
                <p>
                    This is a deposit toward approved work — not the full repair total unless those amounts match.
                </p>
            @else
                <p>
                    This is the amount due on your invoice for work already approved or completed.
                </p>
            @endif
        </div>

        <div class="mt-6 rounded-md border border-slate-200 bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-700">
            <p class="font-semibold text-slate-950">Pay at the shop</p>
            <p class="mt-1">
                Online card payment is not available on this link. Please contact the shop to pay, or ask staff to record your payment in ARK.
            </p>
            {{-- Seam: managed online pay belongs to ARK Platform Payments (future). --}}
        </div>

        @include('portal.partials.vehicle-records-link', [
            'vehicleRecordsLink' => $vehicleRecordsLink ?? null,
            'vehicleName' => $repairOrder->vehicle->display_name,
        ])

        @include('portal.partials._shop-contact-card', [
            'shopPhone' => $shopPhone ?? null,
            'shopPhoneTel' => $shopPhoneTel ?? null,
            'class' => 'mt-4',
        ])
    </section>
</x-portal.app>
