@props([
    'depositAmount',
    'payingRemaining' => false,
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border-2 border-amber-400 bg-amber-50 px-4 py-4 sm:px-5 '.$class]) }}>
    @if ($payingRemaining)
        <p class="text-base font-bold text-amber-950">Remaining balance: {{ $depositAmount }}</p>
        <p class="mt-1 text-sm leading-6 text-amber-900">
            Your deposit is on file. Please contact the shop to pay the remaining {{ $depositAmount }}, or ask staff to record payment in ARK.
        </p>
    @else
        <p class="text-base font-bold text-amber-950">Deposit requested: {{ $depositAmount }}</p>
        <p class="mt-1 text-sm leading-6 text-amber-900">
            Your approvals are saved. Please contact the shop to pay this {{ $depositAmount }} deposit so we can schedule the work, or ask staff to record it in ARK.
        </p>
    @endif
</div>
