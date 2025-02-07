<?php

namespace Livewirez\Webauthn\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Webauthn\Event\AuthenticatorAssertionResponseValidationFailedEvent;

class AuthenticatorAssertionResponseValidationFailedEventListener
{
    public function handle(AuthenticatorAssertionResponseValidationFailedEvent $event)
    {
        app()->make(\Psr\Log\LoggerInterface::class)
        ->error(var_export(['event' => $event, 'e_name' => class_basename($event)], true), [__METHOD__]);
    }
}