<?php 

namespace Livewirez\Webauthn;

use Throwable;
use Cose\Algorithms;
use Webauthn\Util\Base64;
use Psr\Log\LoggerInterface;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialSource;
use Illuminate\Support\Traits\Macroable;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Illuminate\Validation\ValidationException;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredentialRequestOptions;
use Illuminate\Contracts\Foundation\Application;
use Webauthn\PublicKeyCredentialCreationOptions;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticationExtensions\AuthenticationExtension;
use Webauthn\AuthenticationExtensions\AuthenticationExtensions;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

class Webauthn
{
    use Macroable;

    protected ?AuthenticatorAssertionResponseValidator $assertionResponseValidator = null;

    protected ?AuthenticatorAttestationResponseValidator $attestationResponseValidator = null;
    
    protected LoggerInterface $logger;

    protected EventDispatcherInterface $eventDispatcher;

    public const PUBLIC_KEY_REQUEST_OPTIONS_SESSION_KEY = "credentialRequestOptions";
    public const PUBLIC_KEY_CREATION_OPTIONS_SESSION_KEY = "credentialCreationOptions";

    public function __construct(
        protected Application $app,
        protected SerializerInterface $serializer,
        protected WebauthnConfig $config,
        protected ?CeremonyStepManagerFactory $csmFactory = null
    )
    {
        $this->csmFactory ??= new CeremonyStepManagerFactory();

        $this->logger = $this->app->make(LoggerInterface::class);

        $this->eventDispatcher = $this->app->make('webauthn_events');
    }

    public function getAttestationResponseValidator(): AuthenticatorAttestationResponseValidator
    {
        if ($this->attestationResponseValidator === null) {
            $this->attestationResponseValidator = new AuthenticatorAttestationResponseValidator(
                $this->csmFactory->creationCeremony()
            );

            $this->attestationResponseValidator->setEventDispatcher($this->eventDispatcher);

            $this->attestationResponseValidator->setLogger($this->logger);
        }

        return $this->attestationResponseValidator;
    }

    public function getAssertionResponseValidator(): AuthenticatorAssertionResponseValidator
    {    
        if ($this->assertionResponseValidator === null) {
            $this->assertionResponseValidator = AuthenticatorAssertionResponseValidator::create(
                $this->csmFactory->requestCeremony()
            );

            $this->assertionResponseValidator->setEventDispatcher($this->eventDispatcher);

            $this->assertionResponseValidator->setLogger($this->logger);
        }

        return $this->assertionResponseValidator;
    }

    public static function encode(string $string): string
    {
        return Base64UrlSafe::encodeUnpadded($string);
    }

    public static function decode(string $string): string
    {
        return Base64::decode($string);
    }

    public function serializeCredentialOptions(PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions $options): string 
    {
        return $this->serializer->serialize(
            $options,
            'json',
            [ // Optional
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
                JsonEncode::OPTIONS => JSON_THROW_ON_ERROR,
            ]
        );
    }

    public function deserializePublicKeyCredentialRequestOptions(string $request_options): PublicKeyCredentialRequestOptions
    {
        return $this->serializer->deserialize(
            $request_options,
            PublicKeyCredentialRequestOptions::class,
            'json' 
        ); 
    }

    public function deserializePublicKeyCredentialCreationOptions(string $creation_options): PublicKeyCredentialCreationOptions
    {
        return $this->serializer->deserialize(
            $creation_options,
            PublicKeyCredentialCreationOptions::class,
            'json' 
        ); 
    }

    public function getAuthenticationOptions(?PasskeyUserEntityInterface $user = null, bool $serialized = false)
    {
        /** Args:
        *   `rp_id`: The Relying Party's unique identifier as specified in attestations.
        *    (optional) `challenge`: A byte sequence for the authenticator to return back in its response. Defaults to 64 random bytes.
        *    (optional) `timeout`: How long in milliseconds the browser should give the user to choose an authenticator. This value is a *hint* and may be ignored by the browser.
        *    (optional) `allow_credentials`: A list of credentials registered to the user.
        *    (optional) `user_verification`: The RP's preference for the authenticator's enforcement of the "user verified" flag.
        */

        $publicKeyCredentialRequestOptions =
            PublicKeyCredentialRequestOptions::create(
                random_bytes($this->config->get('credential_request_options.challenge_length', 32)), // Challenge
                $this->config->get('credential_request_options.rp_id'),
                [],
                $this->config->get('credential_request_options.user_verification', PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED), // "discouraged" | "preferred" | "required";
                $this->config->get('credential_request_options.timeout', 300),
                AuthenticationExtensions::create(
                    array_map(
                        fn (array $ext) => AuthenticationExtension::create($ext['name'], $ext['value']), 
                        $this->config->get('credential_request_options.extensions')
                    )
                )
            )
        ;
        
        return $serialized ? $this->serializeCredentialOptions($publicKeyCredentialRequestOptions) : $publicKeyCredentialRequestOptions;
    }

    public function getCredentialOptionsForUser(PasskeyUserEntityInterface $user, bool $serialized = false)
    {
        // RP Entity i.e. the application
        $rpEntity = PublicKeyCredentialRpEntity::create(
            $this->config->get('credential_creation_options.rp_entity.name'),   // Name
            $this->config->get('credential_creation_options.rp_entity.id'),     // ID
            $this->config->get('credential_creation_options.rp_entity.icon')      // Icon
        );

        // User Entity
        $userEntity = PublicKeyCredentialUserEntity::create(
            $user->getName(),          // Name
            $user->getId(),            // ID
            $user->getDisplayName(),   // Display name
            $user->getIcon()           // Icon
        );

        // Challenge
        $challenge = random_bytes($this->config->get('credential_creation_options.challenge_length', 32));

        $pubKeyCredParams = array_map(
            fn (int $param) => PublicKeyCredentialParameters::create('public-key', $param), 
            array_unique(array_merge($this->config->get('credential_creation_options.pub_key_cred_params', []), [
                Algorithms::COSE_ALGORITHM_ES256K,
                Algorithms::COSE_ALGORITHM_ES256, 
                Algorithms::COSE_ALGORITHM_RS256,   
                Algorithms::COSE_ALGORITHM_EDDSA,
            ]))
        );

        $authenticatorSelectionCriteria = AuthenticatorSelectionCriteria::create(
            authenticatorAttachment: $this->config->get('credential_creation_options.authenticator_selection_creation_criteria.authenticator_attachment', AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE),
            userVerification: $this->config->get('credential_creation_options.authenticator_selection_creation_criteria.user_verification', AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED),
            residentKey: $this->config->get('credential_creation_options.authenticator_selection_creation_criteria.resident_key', AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED),
        );

        $options = PublicKeyCredentialCreationOptions::create(
            $rpEntity,
            $userEntity,
            $challenge,
            $pubKeyCredParams,
            $authenticatorSelectionCriteria,
            $this->config->get('credential_creation_options.attestation', 'none'), // "direct" | "enterprise" | "indirect" | "none",
            array_map(fn (Passkey $credential) => PublicKeyCredentialDescriptor::create(
                $credential->type, 
                static::decode($credential->public_key_credential_id), 
                $credential->transports
            ), $user->getPasskeys()),
            $this->config->get('credential_creation_options.timeout', 120000)
        );

        return $serialized ? $this->serializeCredentialOptions($options) : $options;
    }

    public function validateAttestationResponse(string $credentials, string|PublicKeyCredentialCreationOptions $creation_options): PublicKeyCredentialSource
    {
        $creation_options = is_string($creation_options) 
        ? $this->serializer->deserialize(
            $creation_options,
            PublicKeyCredentialCreationOptions::class,
            'json' 
        ) 
        : $creation_options;

        $publicKeyCredential = $this->serializer->deserialize(
            $credentials,
            PublicKeyCredential::class,
            'json'
        );

        if (! $publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            //e.g. process here with a redirection to the public key creation page. 
            throw new \RuntimeException('An Authentication Error Occured');
        } 
        
        $authenticatorAttestationResponseValidator = $this->getAttestationResponseValidator();
        
        $publicKeyCredentialSource = $authenticatorAttestationResponseValidator->check(
            $publicKeyCredential->response,
            $creation_options,
            $this->config->get('credential_creation_options.rp_entity.id')
        ); // Throws an Exception

        return $publicKeyCredentialSource;
    }


    public function validateAssertionResponse(string $authentication_response, string|PublicKeyCredentialRequestOptions $request_options): Passkey
    {
        $publicKeyCredential = $this->serializer->deserialize(
            $authentication_response, PublicKeyCredential::class, 'json'
        );

        $request_options = is_string($request_options) 
        ? $this->serializer->deserialize(
            $request_options,
            PublicKeyCredentialRequestOptions::class,
            'json' 
        ) 
        : $request_options;       

        if (! $publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            //e.g. process here with a redirection to the public key login/MFA page. 

            throw ValidationException::withMessages([
                'credentials' => ['Invalid Passkey'],
            ]);
        }
        
        $passkey = Passkey::where(
            'public_key_credential_id', 
            static::encode($publicKeyCredential->rawId)
        )->first();

        if($passkey === null) {
            // Throw an exception if the credential is not found.
            // It can also be rejected depending on your security policy (e.g. disabled by the user because of loss)
            throw ValidationException::withMessages([
                'credentials' => ['Passkey was not found'],
            ]);
        }

        $authenticatorAssertionResponseValidator = $this->getAssertionResponseValidator();

        try {
            $publicKeyCredentialSource = $authenticatorAssertionResponseValidator->check(
                $passkey->transformToWebauthnSource(),
                $publicKeyCredential->response,
                $request_options,
                $this->config->get('credential_request_options.rp_id'),
                null
            );
        } catch (Throwable $th) {
            throw ValidationException::withMessages([
                'credentials' => ['Error validating passkey. Please try again later.'],
            ]);
        }

        $passkey->fillFromWebauthnSource($publicKeyCredentialSource)->update([
            'last_used_at' => now(),
            'usage_count' => ++$passkey->usage_count,
        ]);

        $passkey->public_key_credential_source_object = $publicKeyCredentialSource;

        return $passkey;
    }
}