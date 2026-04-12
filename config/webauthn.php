<?php

use Cose\Algorithms;
use Livewirez\Webauthn\Webauthn;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialRequestOptions;

return [

    'guard' => ['web'],

    'override_listeners' => false,

    'middleware' => ['web'],

    'passkeys_table' => env('WEBAUTHN_PASSKEYS_TABLE', 'passkeys'),

    'credential_creation_options' => [
        'rp_entity' => [
            'name' => env('WEBAUTHN_RP_NAME', env('APP_NAME', 'Laravel')),
            'id' => env('WEBAUTHN_RP_HOST', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
            'icon' => null
        ],
    
        'challenge_length' => env('WEBAUTH_CHALLENGE_LENGTH', 32), // $challenge = random_bytes(32);
    
        'pub_key_cred_params' => [
            Algorithms::COSE_ALGORITHM_ES256K,    // More interesting algorithm
            Algorithms::COSE_ALGORITHM_ES256,     //      ||
            Algorithms::COSE_ALGORITHM_RS256,     //      || 
            Algorithms::COSE_ALGORITHM_EDDSA,     //      ||
            // Algorithms::COSE_ALGORITHM_PS256,  //      \/
            // Algorithms::COSE_ALGORITHM_ED256,  // Less interesting algorithm
        ],
    
        'authenticator_selection_creation_criteria' => [
            'authenticator_attachment' => AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE, // null | "platform" | "cross-platform"
            'user_verification' => AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            'resident_key' => AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
        ],
    
        'attestation' => env('WEBAUTHN_ATTESTATION', 'none') , // "direct" | "enterprise" | "indirect" | "none",
    
        'timeout' => env('WEBAUTHN_CREDENTIAL_CREATION_TIMEOUT', 120000),
        
        'extensions' => [
            'uvm' => true
        ]
    ],

    'credential_request_options' => [
        'challenge_length' => env('WEBAUTH_CHALLENGE_LENGTH', 32), // $challenge = random_bytes(32);

        'rp_id' => env('WEBAUTHN_RP_HOST', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),

        'user_verification' => env('WEBAUTHN_USER_VERIFICATION' , PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED), // "discouraged" | "preferred" | "required";,

        'timeout' => 300,

        'extensions' => [
            ['name' => 'loc', 'value' => true ],
            ['name' => 'txAuthSimple', 'value' => 'Please log in with a registered authenticator'],
        ]
    ],

    'login_redirect_route' => config('WEBAUTHN_LOGIN_REDIRECT_ROUTE', 'dashboard'),

    // In case you want 2FA with Laravel Fortify, you can disable the default login route and use the one provided by this package, which will work alongside 2FA.
    // stops users bypassing 2FA if thy have stolen passkeys
    'enable_login_route' => class_exists(\Laravel\Fortify\Features::class) && \Laravel\Fortify\Features::enabled(\Laravel\Fortify\Features::twoFactorAuthentication()) ? 
        env('WEBAUTHN_ENABLE_LOGIN_ROUTE', false) : true,
];