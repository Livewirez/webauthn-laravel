<?php 

namespace Livewirez\Webauthn;

use Illuminate\Support\Fluent;
use Webauthn\Event\AuthenticatorAssertionResponseValidationFailedEvent;
use Webauthn\Event\AuthenticatorAttestationResponseValidationFailedEvent;
use Webauthn\Event\AuthenticatorAssertionResponseValidationSucceededEvent;
use Webauthn\Event\AuthenticatorAttestationResponseValidationSucceededEvent;

class WebauthnConfig extends Fluent
{
    /**
     * Listeners for when the attestation response validation is succeeded.
     * 
     * @var (callable(\Webauthn\Event\AuthenticatorAttestationResponseValidationSucceededEvent): void)[] 
     */
    public static array $attestationResponseValidationSucceededListeners = [];

    /**
     * Listeners for when the attestation response validation has failed.
     * 
     * @var (callable(\Webauthn\Event\AuthenticatorAttestationResponseValidationFailedEvent): void)[] 
     */
    public static array $attestationResponseValidationFailedListeners = [];


    /**
     * Listeners for when the assertion response validation is succeeded.
     * 
     * @var (callable(\Webauthn\Event\AuthenticatorAssertionResponseValidationSucceededEvent): void)[] 
     */
    public static array $assertionResponseValidationSucceededListeners = [];

    /**
     * Listeners for when the assertion response validation is succeeded.
     * 
     * @var (callable(\Webauthn\Event\AuthenticatorAssertionResponseValidationFailedEvent): void)[] 
     */
    public static array $assertionResponseValidationFailedListeners = [];

    

    


}