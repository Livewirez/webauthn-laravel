<?php 

namespace Livewirez\Webauthn;

use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewirez\Webauthn\WebauthnConfig;
use Illuminate\Support\ServiceProvider;
use Livewirez\Webauthn\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Symfony\Component\Serializer\SerializerInterface;
use Livewirez\Webauthn\Http\Controllers\PasskeyController;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\Event\AuthenticatorAssertionResponseValidationFailedEvent;
use Webauthn\Event\AuthenticatorAttestationResponseValidationFailedEvent;
use Webauthn\Event\AuthenticatorAssertionResponseValidationSucceededEvent;
use Webauthn\Event\AuthenticatorAttestationResponseValidationSucceededEvent;
use Livewirez\Webauthn\Http\Controllers\PasskeyAuthenticatedSessionController;
use Livewirez\Webauthn\Listeners\AuthenticatorAssertionResponseValidationFailedEventListener;
use Livewirez\Webauthn\Listeners\AuthenticatorAttestationResponseValidationFailedEventListener;
use Livewirez\Webauthn\Listeners\AuthenticatorAssertionResponseValidationSucceededEventListener;
use Livewirez\Webauthn\Listeners\AuthenticatorAttestationResponseValidationSucceededEventListener;

class WebauthnServiceProvider extends ServiceProvider
{
    // fired after everything in the application including 3rd party libraries have been loaded up
    // bootstrap web services
    // listen for events
    // publish configuration files or database migrations
    public function boot()
    {
        $this->configureMigrations();
        $this->configureEvents();
        $this->defineRoutes();
        $this->configureGuard();
        $this->configurePolicies();
    }

    // great for extending functionality to your current service provider class before the application is ready
    // through singletons or other service providers
    // extend functionality from other classes
    // register service providers
    // create singleton classes
    public function register()
    {
        $this->app->bind(SerializerInterface::class, function () {
            // The manager will receive data to load and select the appropriate 
            $attestationStatementSupportManager = AttestationStatementSupportManager::create();

            $attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());

            $factory = new WebauthnSerializerFactory($attestationStatementSupportManager);

            return $factory->create();
        });

        $this->app->singleton('webauthn_serializer', fn (Application $app) => $app->make(SerializerInterface::class));

        $this->app->singleton(
            'webauthn_events', 
            fn (Application $app) => new Dispatcher(
                $app->make('events')
            )
        );

        if (! app()->configurationIsCached()) {
            $this->mergeConfigFrom(__DIR__.'/../config/webauthn.php', 'webauthn');
        }

        $this->configureWebauthn();
    }

    public function configureWebauthn()
    {
        $this->app->singleton(
            Webauthn::class, 
            fn (Application $app) => new Webauthn(
                $app,
                $app->make(SerializerInterface::class),
                new WebauthnConfig(config('webauthn'))
            )
        );
        $this->app->singleton(
            'webauthn',
            fn (Application $app): Webauthn => $app->make(Webauthn::class)
        );

        $this->app->singleton(PasskeyGuard::class, fn (Application $app) => new PasskeyGuard($app->make(Webauthn::class)));
    }


    protected function configureEvents()
    {
        $override_listeners = config('webauthn.override_listeners', false);

        $assertionResponseValidationFailedListeners = [
            ...WebauthnConfig::$assertionResponseValidationFailedListeners, 
            ...($override_listeners ? [] : [AuthenticatorAssertionResponseValidationFailedEventListener::class])
        ];

        $assertionResponseValidationSucceededListeners = [  
            ...WebauthnConfig::$assertionResponseValidationSucceededListeners, 
            ...($override_listeners ? [] : [AuthenticatorAssertionResponseValidationSucceededEventListener::class])
        ];

        $attestationResponseValidationFailedListeners = [
            ...WebauthnConfig::$attestationResponseValidationFailedListeners, 
            ...($override_listeners ? [] : [AuthenticatorAttestationResponseValidationFailedEventListener::class])
        ];

        $attestationResponseValidationSucceededListeners = [
            ...WebauthnConfig::$attestationResponseValidationSucceededListeners, 
            ...($override_listeners ? [] : [AuthenticatorAttestationResponseValidationSucceededEventListener::class])
        ];

        foreach ($assertionResponseValidationFailedListeners as $listener) {
            Event::listen(
                AuthenticatorAssertionResponseValidationFailedEvent::class,
                $listener
            );
        }

        foreach ($assertionResponseValidationSucceededListeners as $listener) {
            Event::listen(
                AuthenticatorAssertionResponseValidationSucceededEvent::class,
                $listener
            );
        }

        foreach ($attestationResponseValidationFailedListeners as $listener) {
            Event::listen(
                AuthenticatorAttestationResponseValidationFailedEvent::class,
                $listener
            );
        }

        foreach ($attestationResponseValidationSucceededListeners as $listener) {
            Event::listen(
                AuthenticatorAttestationResponseValidationSucceededEvent::class,
                $listener
            );
        }

    }

    protected function configureMigrations()
    {
        // php artisan vendor:publish

        if (app()->runningInConsole()) {
            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'webauthn-migrations');

            $this->publishes([
                __DIR__.'/../config/webauthn.php' => config_path('webauthn.php'),
            ], 'webauthn-config');
        }
    }

    
    /**
     * Define the Sanctum routes.
     *
     * @return void
     */
    protected function defineRoutes()
    {
        Route::prefix('passkeys')->name('passkeys.')->group(function () {
            Route::get('generate-authentication-options', [PasskeyAuthenticatedSessionController::class, 'create'])
                ->middleware(['web', 'guest']) 
                ->name('webauthn.passkeys.register_request_options');
    
            Route::post(
                'verify-authentication',
                [PasskeyAuthenticatedSessionController::class, 'store']
            )->middleware(['web', 'guest'])->name('webauthn.passkeys.login');
    
            Route::get('generate-registration-options', [PasskeyController::class, 'create'])
                ->middleware(['web', 'auth']) 
                ->name('webauthn.passkeys.register_creation_options');
    
            Route::post('verify-registration', [PasskeyController::class, 'store'])
                ->middleware(['web', 'auth']) 
                ->name('webauthn.passkeys.store');
        });
    }

    protected function configureGuard()
    {
        Auth::resolved(function ($auth, Application $app) {

            $passkeyGuard = new PasskeyGuard($app->make(Webauthn::class));

            SessionGuard::macro('attemptPasskeyAuthentication',  $passkeyGuard->getPasskeySessionGuardAuthenticationCallback());

            $auth->viaRequest('webauthn', $passkeyGuard);
        });
    }

    protected function configurePolicies()
    {
        Gate::policy(Passkey::class, PasskeyPolicy::class);
    }
}