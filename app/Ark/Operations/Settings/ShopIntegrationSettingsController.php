<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Cloud\CloudConnection;
use App\Ark\Mail\ArkMailActivationClient;
use App\Ark\Mail\ArkMailIdentityClient;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Settings\Concerns\InteractsWithShopSettingsPersistence;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopIntegrationSettingsController
{
    use Concerns\InteractsWithShopSettingsPersistence;
    public function __construct(
        private readonly EstimateDocumentService $estimateDocumentService,
        private readonly EstimateTotalsCalculator $estimateTotalsCalculator,
    ) {}

    protected function estimateDocuments(): EstimateDocumentService
    {
        return $this->estimateDocumentService;
    }

    protected function totalsCalculator(): EstimateTotalsCalculator
    {
        return $this->estimateTotalsCalculator;
    }

public function updatePayments(Request $request): RedirectResponse
    {
        return redirect()
            ->route('operations.settings.shop.edit')
            ->with('status', 'Card processor settings are not configured in Core. Record external payments on the repair order. Managed processors belong to ARK Cloud Payments.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'postmark_reply_to' => ['nullable', 'email', 'max:255'],
            'postmark_reply_to_name' => ['nullable', 'string', 'max:255'],
        ]);

        ShopSettings::current()->persistTrusted([
            'postmark_reply_to' => $this->nullableTrimmedString($data['postmark_reply_to'] ?? null),
            'postmark_reply_to_name' => $this->nullableTrimmedString($data['postmark_reply_to_name'] ?? null),
        ]);

        $redirect = redirect()
            ->route('operations.settings.shop.edit', ['section' => 'customer-messaging'])
            ->with('status', 'Email settings saved.');

        if (CloudConnection::current()->isConnected()) {
            $synced = app(ArkMailIdentityClient::class)->syncShopReplyTo();
            if (! $synced) {
                $redirect->with('warning', 'Reply-To was saved here, but ARK Cloud could not be updated right now. Try again from Settings → ARK Cloud after Cloud is reachable.');
            }
        }

        return $redirect;
    }

    /** @deprecated Use ShopCloudSettingsController — redirects preserved for old links. */
    public function enableArkMail(Request $request, ArkMailActivationClient $activation): RedirectResponse
    {
        return app(ShopCloudSettingsController::class)->connect($request, $activation);
    }

    /** @deprecated Use ShopCloudSettingsController */
    public function claimArkMail(Request $request, ArkMailActivationClient $activation): RedirectResponse
    {
        return app(ShopCloudSettingsController::class)->claim($request, $activation);
    }

    /** @deprecated Use ShopCloudSettingsController */
    public function disconnectArkMail(ArkMailActivationClient $activation): RedirectResponse
    {
        return app(ShopCloudSettingsController::class)->disconnect($activation);
    }
}
