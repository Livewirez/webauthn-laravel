
# Webauthn - Laravel - Hello Passkeys, Bye-Bye Passwords

![PHP Version](https://img.shields.io/packagist/php-v/livewirez/webauthn-laravel)
![Laravel Version](https://img.shields.io/packagist/dependency-v/livewirez/webauthn-laravel/illuminate/support)
![License](https://img.shields.io/github/license/Livewirez/webauthn-laravel)
![Downloads](https://img.shields.io/packagist/dt/livewirez/webauthn-laravel)
![Stars](https://img.shields.io/github/stars/Livewirez/webauthn-laravel)

Webauthn defines an API enabling the creation and use of strong, attested, scoped, public key-based credentials by web applications, for the purpose of strongly authenticating users.

A laravel package for authenticating with webauthn passkeys on laravel applications. This makes it easier to integrate passkeys for authenticating users.

- [**Sources**](#sources)
- [**Info**](#info)
- [**Installation**](#installation)
- [**Usage**](#usage)
  - [***Basic Usage***](#basic-usage)
    - [**Retrieving a users' passkeys**](#retrieving-passkeys-for-user)
    - [**Creating a Passkey**](#creating-a-passkey)
    - [**Logging in with a Passkey**](#logging-in-with-a-passkey)
- [**Config**](#config)
- [**Events**](#events)
- [**License**](#license)

## Sources

- [Web Authenitcation Guide](https://w3c.github.io/webauthn/#sctn-intro)

- [Web Authentication Api](https://developer.mozilla.org/en-US/docs/Web/API/Web_Authentication_API)

- [Webauthn Guide](https://webauthn.guide/#about-webauthn)

- [Webauthn PHP](https://github.com/lbuchs/WebAuthn)

## Info

- under the hood this package is a wrapper for [Webauthn PHP package](https://github.com/web-auth/webauthn-framework) using [This Documentation](https://webauthn-doc.spomky-labs.com)

## Installation

You can install the package via composer:

```bash
composer require livewirez/webauthn-laravel
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="webauthn-laravel-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="webauthn-laravel-config"
```

Add the `Livewirez\Webauthn\PasskeyAuthenticatable` and `Livewirez\Webauthn\PasskeyAuthenticatableTrait` to any `User` model that implements the `Illuminate\Contracts\Auth\Authenticatable` interface.

Add this package from [Simple Webauthn](https://simplewebauthn.dev/docs/packages/browser)

```bash
npm install @simplewebauthn/browser
```

which provides to the front end a specific collection of values that the hardware authenticator will understand for "registration" and "authentication".

## Usage

### `Livewirez\Webauthn\PasskeyAuthenticatable` interface

This interface has methods used to configure the  [user entity](https://w3c.github.io/webauthn/#dictionary-user-credential-params)—which is used to generate a pass key during the [`Attestation` phase](https://developer.mozilla.org/en-US/docs/Web/API/Web_Authentication_API/Attestation_and_Assertion#attestation).

```php
<?php

namespace Livewirez\Webauthn;

use Illuminate\Contracts\Auth\Authenticatable;

interface PasskeyAuthenticatable extends Authenticatable
{
    public function getName(): string;

    public function getDisplayName(): string;

    public function getId(): string;

    public function getIcon(): ?string;

    public function getPasskeys(): array;
}
```

The user model

```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Notifications\Notifiable;
use Livewirez\Webauthn\PasskeyAuthenticatableTrait;
use Livewirez\Webauthn\PasskeyAuthenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements PasskeyAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatableTrait;
    
    // ...
}
```

### Basic usage

#### Retrieving passkeys for user

you can use the `passkeys` method on the user object to get the users saved passkeys and display them on their profile

using inertia

```php
<?php

namespace App\Http\Controllers\Settings;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Livewirez\Webauthn\Passkey;
use Symfony\Component\Uid\Uuid;
use App\Http\Controllers\Controller;

class PasskeyController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('settings/Passkeys', [
            'passkeys' => $request->user()->passkeys()->get()->map(static function (Passkey $source) {
                $data = $source->only(['id', 'name', 'public_key_credential_id', 'counter', 'aaguid', 'user_handle', 'backup_status', 'backup_eligible', 'usage_count']);
                $data['aaguid'] = Uuid::fromString($source->data->aaguid)->toRfc4122();
                $data['public_key_credential_id_hex'] = bin2hex($data['public_key_credential_id']);
                $data['last_used_at'] = $source->last_used_at ? (new \DateTimeImmutable($source->last_used_at))->format('j M Y, g:i a') : null;

                return (object) $data;
            }),
            'status' => $request->session()->get('status'),
        ]);
    }
}
```

or using blade

```php

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'passkeys' => $request->user()->passkeys()->get()->map(static function (Passkey $source) {
                $data = $source->only(['id', 'name', 'usage_count']);
                $data['aaguid'] = Uuid::fromString($source->data->aaguid)->toRfc4122();
                $data['last_used_at'] = $source->last_used_at?->format('j M Y, g:i a');
    
                return (object) $data;
            })
        ]);
    }
}
```

or in the case of laravel jetstream override the show method of `Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Jetstream\Agent;
use Laravel\Fortify\Features;
use Illuminate\Support\Carbon;
use Livewirez\Webauthn\Passkey;
use Symfony\Component\Uid\Uuid;
use Laravel\Jetstream\Jetstream;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController as BaseUserProfileController;

class UserProfileController extends BaseUserProfileController
{
    /**
     * Show the general profile settings screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    #[\Override]
    public function show(Request $request)
    {
        $this->validateTwoFactorAuthenticationState($request);

        return Jetstream::inertia()->render($request, 'Profile/Show', [
            'confirmsTwoFactorAuthentication' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'sessions' => $this->sessions($request)->all(),
            'passkeys' => $request->user()->passkeys()->get()->map(static function (Passkey $source) {
                $data = $source->only(['id', 'name', 'usage_count']);
                $data['aaguid'] = Uuid::fromString($source->data->aaguid)->toRfc4122();
                $data['last_used_at'] = $source->last_used_at?->format('j M Y, g:i a');
    
                return (object) $data;
            })
        ]);
    }
}
```

and add the routes

- Inertia

```php
<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    Route::get('settings/passkeys', [PasskeyController::class, 'index'])->name('passkeys');

});
```

- Blade

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('settings/passkeys', [PasskeyController::class, 'index'])->middleware('auth')->name('passkeys');
```

- Jetstream

```php
<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserProfileController;

Route::group(['middleware' => config('jetstream.middleware', ['web'])], function () {

    $authMiddleware = config('jetstream.guard')
    ? 'auth:'.config('jetstream.guard')
    : 'auth';

    $authSessionMiddleware = config('jetstream.auth_session', false)
        ? config('jetstream.auth_session')
        : null;

    Route::group(['middleware' => array_values(array_filter([$authMiddleware, $authSessionMiddleware]))], function () {
        
        Route::get('/user/profile', [UserProfileController::class, 'show'])
        ->name('profile.show');
    });

});
```

#### Creating a passkey

using the different javascript templates from the [`js` folder](js) `(PasskeyCreate component for frameworks and  app.js / profile -> partials -> handle-user-passkeys.blade.php for blade_alipnejs)`, you will need to first fetch the credential creation options from `'/passkeys/generate-registration-options'` route from your frontend and pass them to the `startRegistration` function from `'@simplewebauthn/browser'`

```js
import { startRegistration } from '@simplewebauthn/browser';
```

```js
axios.get('/passkeys/generate-registration-options') 
```

or

```js
fetch('/passkeys/generate-registration-options')
```

then if everything is ok it will use the `attResp` to post a json string of it to
`'/passkeys/verify-registration'`. if everything is ok the passkey should be created successfully

```js
axios.get('/passkeys/generate-registration-options')
            .then(async (response) => {
                try {
                    // Parse the public key credential creation options
                    const publicKeyCredentialCreationOptions = JSON.parse(response.data.publicKeyCredentialCreationOptions);
                    
                    // Start registration process
                    const attResp = await startRegistration({ optionsJSON: publicKeyCredentialCreationOptions });

                      const verificationResponse = await axios.post('/passkeys/verify-registration', { name: '', credentials: JSON.stringify(attResp) });

                      window.location.reload()
                } catch (error) {
                    // handle startRegistration Error
                }
            })
            .catch(error => {
                // handle network error
            })

```

#### Logging in with a passkey

like creating a passkey, you will net to get the options that the server will use for verification

using the different javascript templates from the [`js` folder](js) `(PasskeyLogin component for frameworks and  app.js / auth -> partials -> passkeys-login.blade.php for blade_alipnejs)`,

```js
import { startAuthentication } from '@simplewebauthn/browser';
```

```js
axios.get('/passkeys/generate-authentication-options') 
```

or

```js
fetch('/passkeys/generate-authentication-options')
```

then if everything is ok it will use the `attResp` to post a json string of it to
`'/passkeys/verify-authentication'`. if everything is ok the passkey should be created successfully

```js
axios.get('/passkeys/generate-registration-options')
            .then(async (response) => {
                try {
                     const publicKeyCredentialRequestOptions = JSON.parse(
                        response.data.publicKeyCredentialRequestOptions
                    );

                    // Start authentication process
                    const authResponse = await startAuthentication(
                        publicKeyCredentialRequestOptions
                    );

                    // Verify authentication with server
                    const verificationResponse = await axios.post('/passkeys/verify-authentication', {
                        credentials: JSON.stringify(authResponse),
                        credentials_id: authResponse.id
                    });

                    // Handle successful login
                    if (verificationResponse.data.redirect) {
                        window.location.href = verificationResponse.data.redirect;
                    }

                } catch (error) {
                    // handle startRegistration Error
                }
            })
            .catch(error => {
                // handle network error
            })

```

if successfull it will return a json response with a redirect url in the redirect key or just redirect if the requests were made normally

## Laravel 12 update

if you're using the new starter kits for react or vue using inertia, you can use the router function to make requests, but if you want to use axios, which may not be installed,
you can in install it

```bash
npm i axios
```

then create a `bootstrap.ts` file

```js
import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

and then in `app.ts`

```js
import './bootstrap';
import { AxiosInstance } from 'axios';

declare global {
    interface Window {
        axios: AxiosInstance;
    }
}
```

## Config

you can customize the configuration

```php
// config/webauthn.php

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
    ]
];
```

## Events

You can listen for events fired from the base package in `AppServiceProvider`

```php
<?php

namespace App\Providers;

use Livewirez\Webauthn\WebauthnConfig;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
       $this->registerWebauthnCustomEventListeners();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        
    }

    protected function registerWebauthnCustomEventListeners()
    {
        WebauthnConfig::$assertionResponseValidationFailedListeners = [
            fn ($event) => \Illuminate\Support\Facades\Log::error(var_export(['event' => $event, 'e_name' => class_basename($event)], true), ['__assertionResponseValidationFailedListeners__Closure'])
        ];

        WebauthnConfig::$assertionResponseValidationSucceededListeners = [
            fn ($event) => \Illuminate\Support\Facades\Log::info(var_export(['event' => $event, 'e_name' => class_basename($event)], true), ['__assertionResponseValidationSucceededListeners__Closure'])
        ];

        WebauthnConfig::$attestationResponseValidationFailedListeners = [
            fn ($event) => \Illuminate\Support\Facades\Log::error(var_export(['event' => $event, 'e_name' => class_basename($event)], true), ['__attestationResponseValidationFailedListeners__Closure'])
        ];

        WebauthnConfig::$attestationResponseValidationSucceededListeners = [
            fn ($event) => \Illuminate\Support\Facades\Log::info(var_export(['event' => $event, 'e_name' => class_basename($event)], true), ['__attestationResponseValidationSucceededListeners__Closure'])
        ];
    }
}

```

There are eventd fired for when passkey registration is successful or has failed , and when logging in with a passkey is successful or has failed.

you can choose to not use the default listeners by setting the `override_listeners` to true in `config/webauthn.php`

## License

Webauthn - Laravel is open-source software released under the MIT license. See [LICENSE](LICENSE) for more information.
