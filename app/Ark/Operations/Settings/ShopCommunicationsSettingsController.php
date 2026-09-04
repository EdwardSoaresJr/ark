<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopCommunicationsSettingsController
{
    public function updateCustomerMessaging(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'google_reviews_url' => ['nullable', 'url', 'max:2048'],
            'postmark_reply_to' => ['nullable', 'email', 'max:255'],
            'postmark_reply_to_name' => ['nullable', 'string', 'max:255'],
            'telephony_call_flow' => ['nullable', 'array'],
            'telephony_call_flow.comms_attention_gate_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.comms_escalation_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.comms_escalation_delay_minutes' => ['nullable', 'integer', 'min:1', 'max:30'],
            'telephony_call_flow.comms_escalation_cooldown_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'telephony_call_flow.comms_browser_notifications_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.missed_call_rescue_enabled' => ['nullable', 'boolean'],
            'telephony_call_flow.missed_call_rescue_delay_seconds' => ['nullable', 'integer', 'min:10', 'max:3600'],
            'telephony_call_flow.missed_call_rescue_cooldown_minutes' => ['nullable', 'integer', 'min:30', 'max:4320'],
            'telephony_call_flow.missed_call_rescue_text_open' => ['nullable', 'string', 'max:500'],
            'telephony_call_flow.missed_call_rescue_text_closed' => ['nullable', 'string', 'max:500'],
            'message_actions' => ['nullable', 'array'],
            'message_actions.tow_company' => ['nullable', 'string', 'max:120'],
            'message_actions.tow_phone' => ['nullable', 'string', 'max:32'],
            'message_actions.tow_notes' => ['nullable', 'string', 'max:500'],
            'message_actions.wifi_ssid' => ['nullable', 'string', 'max:120'],
            'message_actions.wifi_password' => ['nullable', 'string', 'max:120'],
            'message_actions.after_hours_pickup' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = ShopSettings::current();
        $callFlowInput = is_array($data['telephony_call_flow'] ?? null) ? $data['telephony_call_flow'] : [];
        $existingFlow = TelephonyCallFlowSettings::fromShopSettings($settings)->toArray();

        $callFlow = array_merge($existingFlow, [
            'comms_attention_gate_enabled' => $request->boolean(
                'telephony_call_flow.comms_attention_gate_enabled',
                (bool) ($existingFlow['comms_attention_gate_enabled'] ?? true),
            ),
            'comms_escalation_enabled' => $request->boolean(
                'telephony_call_flow.comms_escalation_enabled',
                (bool) ($existingFlow['comms_escalation_enabled'] ?? true),
            ),
            'comms_escalation_delay_minutes' => (int) ($callFlowInput['comms_escalation_delay_minutes'] ?? $existingFlow['comms_escalation_delay_minutes'] ?? 3),
            'comms_escalation_cooldown_minutes' => (int) ($callFlowInput['comms_escalation_cooldown_minutes'] ?? $existingFlow['comms_escalation_cooldown_minutes'] ?? 30),
            'comms_browser_notifications_enabled' => $request->boolean(
                'telephony_call_flow.comms_browser_notifications_enabled',
                (bool) ($existingFlow['comms_browser_notifications_enabled'] ?? true),
            ),
            'missed_call_rescue_enabled' => $request->boolean(
                'telephony_call_flow.missed_call_rescue_enabled',
                (bool) ($existingFlow['missed_call_rescue_enabled'] ?? false),
            ),
            'missed_call_rescue_delay_seconds' => (int) ($callFlowInput['missed_call_rescue_delay_seconds'] ?? $existingFlow['missed_call_rescue_delay_seconds'] ?? 120),
            'missed_call_rescue_cooldown_minutes' => (int) ($callFlowInput['missed_call_rescue_cooldown_minutes'] ?? $existingFlow['missed_call_rescue_cooldown_minutes'] ?? 60),
            'missed_call_rescue_text_open' => array_key_exists('missed_call_rescue_text_open', $callFlowInput)
                ? trim((string) ($callFlowInput['missed_call_rescue_text_open'] ?? ''))
                : (string) ($existingFlow['missed_call_rescue_text_open'] ?? ''),
            'missed_call_rescue_text_closed' => array_key_exists('missed_call_rescue_text_closed', $callFlowInput)
                ? trim((string) ($callFlowInput['missed_call_rescue_text_closed'] ?? ''))
                : (string) ($existingFlow['missed_call_rescue_text_closed'] ?? ''),
        ]);

        $messageActionsInput = is_array($data['message_actions'] ?? null) ? $data['message_actions'] : [];

        $settings->persistTrusted([
            'telephony_call_flow' => $callFlow,
            'google_reviews_url' => $this->nullableTrimmedString($data['google_reviews_url'] ?? null),
            'postmark_reply_to' => $this->nullableTrimmedString($data['postmark_reply_to'] ?? null),
            'postmark_reply_to_name' => $this->nullableTrimmedString($data['postmark_reply_to_name'] ?? null),
            'message_actions' => [
                'tow_company' => $this->nullableTrimmedString($messageActionsInput['tow_company'] ?? null),
                'tow_phone' => $this->nullableTrimmedString($messageActionsInput['tow_phone'] ?? null),
                'tow_notes' => $this->nullableTrimmedString($messageActionsInput['tow_notes'] ?? null),
                'wifi_ssid' => $this->nullableTrimmedString($messageActionsInput['wifi_ssid'] ?? null),
                'wifi_password' => $this->nullableTrimmedString($messageActionsInput['wifi_password'] ?? null),
                'after_hours_pickup' => $this->nullableTrimmedString($messageActionsInput['after_hours_pickup'] ?? null),
            ],
        ]);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'customer-messaging'])
            ->with('status', 'Customer messaging settings saved.');
    }

    private function nullableTrimmedString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
