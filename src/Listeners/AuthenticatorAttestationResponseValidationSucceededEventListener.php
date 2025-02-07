<?php

namespace Livewirez\Webauthn\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Webauthn\Event\AuthenticatorAttestationResponseValidationSucceededEvent;

class AuthenticatorAttestationResponseValidationSucceededEventListener
{
    public function handle(AuthenticatorAttestationResponseValidationSucceededEvent $event)
    {
        app()->make(\Psr\Log\LoggerInterface::class)
        ->info(var_export(['event' => $event, 'e_name' => class_basename($event)], true), [__METHOD__]);
    }
}