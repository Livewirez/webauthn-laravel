<?php 

namespace Livewirez\Webauthn;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Timebox;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewirez\Webauthn\Events\PasskeyLoginFailed;
use Livewirez\Webauthn\Events\PasskeyLoginSuccess;

class PasskeyGuard
{
    public static $passkeySessionGuardAuthenticationCallback;

    public static $callback;

    public function __construct(
        protected Webauthn $webauthn,
        protected ?Timebox $timebox = null,
        int $timeboxDuration = 200000,
    ) {
        $this->timebox ??= new Timebox;
    }

    public function __invoke(Request $request, ?UserProvider $provider): PasskeyAuthenticatable
    {
        if (isset(static::$callback)) {
            return call_user_func(static::$callback, $request, $provider);
        }

        foreach (Arr::wrap(config('webauthn.guard', 'web')) as $guard) {
            if ($user = Auth::guard($guard)->user()) {
                return $user;
            }
        }
        
        $credentials = $request->validate([
            'credentials' => ['required', 'string']
        ]);

        $passkey = $this->webauthn->validateAssertionResponse(
            $credentials['credentials'], 
            $request->session()->get($this->webauthn::PUBLIC_KEY_REQUEST_OPTIONS_SESSION_KEY)
        );

        return $user = $passkey->passkey_user()->firstOrFail();
    }

    public function getPasskeySessionGuardAuthenticationCallback(): callable
    {
        $webauthn = $this->webauthn;

        if (static::$passkeySessionGuardAuthenticationCallback) {
            return static::$passkeySessionGuardAuthenticationCallback;
        }

        return function (array $credentials) use ($webauthn) {

            return $this->timebox->call(function (Timebox $timebox) use ($credentials, $webauthn) {
                $this->fireAttemptEvent($credentials);

                try {
                    $passkey = $webauthn->validateAssertionResponse(
                        $credentials['credentials'], 
                        $this->session->get($webauthn::PUBLIC_KEY_REQUEST_OPTIONS_SESSION_KEY)
                    );
            
                    $this->lastAttempted = $user = $passkey->passkey_user()->firstOrFail();

                    if ($user) {
                        $this->fireValidatedEvent($user);

                        $this->login($user);

                        event(new PasskeyLoginSuccess($user, $this->name));

                        $timebox->returnEarly();

                        return true;
                    }
                 
                   
                    $this->fireFailedEvent($user, $credentials);

                    event(new PasskeyLoginFailed($this->name, $credentials, $user));

                    return false;

                } catch (\Throwable $th) {
                    $this->fireFailedEvent(null, $credentials);

                    event(new PasskeyLoginFailed($this->name, $credentials, null));

                    return false;
                }

            }, $this->timeboxDuration);
        };
    }
}