<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Cloud\ArkCloudConnectOrchestrator;
use App\Ark\Cloud\Http\ArkCloudConnectController;
use App\Ark\Mail\ArkMailActivationClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopCloudSettingsController
{
    private function redirectToArkCloud(): RedirectResponse
    {
        return redirect()->route('operations.settings.shop.edit', [
            'section' => 'ark-cloud',
        ]);
    }

    public function connect(Request $request, ArkCloudConnectOrchestrator $orchestrator): RedirectResponse
    {
        $data = $request->validate([
            'ark_mail_service_url' => ['nullable', 'url', 'max:255'],
        ]);

        try {
            $serviceUrl = filled($data['ark_mail_service_url'] ?? null)
                ? rtrim((string) $data['ark_mail_service_url'], '/')
                : null;
            $returnTo = route('operations.cloud.connecting', absolute: true);
            $started = $orchestrator->begin($serviceUrl, $returnTo);
        } catch (\Throwable $e) {
            return $this->redirectToArkCloud()->with('status', 'Could not start connecting: '.$e->getMessage());
        }

        return redirect()->away($started['connect_url']);
    }

    public function claim(Request $request, ArkMailActivationClient $activation): RedirectResponse
    {
        $data = $request->validate([
            'pairing_public_id' => ['nullable', 'uuid'],
        ]);

        try {
            $activation->claimPairing($data['pairing_public_id'] ?? null);
        } catch (\Throwable $e) {
            return $this->redirectToArkCloud()->with('status', 'Could not finish connecting: '.$e->getMessage());
        }

        return $this->redirectToArkCloud()->with('status', 'ARK Cloud connected.');
    }

    public function disconnect(ArkMailActivationClient $activation): RedirectResponse
    {
        $activation->disconnect();

        return $this->redirectToArkCloud()->with('status', 'ARK Cloud disconnected.');
    }
}
