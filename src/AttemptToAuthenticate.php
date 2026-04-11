<?php

namespace Livewirez\Webauthn;

use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttemptToAuthenticate
{
    public static $passkeySessionGuardAuthenticationCallback;


    /**
     * Create a new controller instance.
     *
     * @param  \Illuminate\Contracts\Auth\StatefulGuard  $guard
     * @param  \Laravel\Fortify\LoginRateLimiter  $limiter
     * @return void
     */
    public function __construct(
        protected StatefulGuard $guard, protected LoginRateLimiter $limiter
    )
    {
        
    }

        /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @param  callable  $next
     * @return mixed
     *
     * @throws ValidationException
     */
    public function handle(Request $request, callable $next)
    {
        if (static::$passkeySessionGuardAuthenticationCallback) {
            return $this->handleUsingCustomCallback($request, $next);
        }

        if ($this->guard->attemptPasskeyAuthentication(
            $request->only(['credentials', 'credentials_id']))
        ) {
            return $next($request);
        }

        $this->throwFailedAuthenticationException($request);
    }

    
    /**
     * Attempt to authenticate using a custom callback.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return mixed
     *
     * @throws ValidationException
     */
    protected function handleUsingCustomCallback($request, $next)
    {
        $user = call_user_func(static::$passkeySessionGuardAuthenticationCallback, $request);

        if (! $user) {
            $this->fireFailedEvent($request);

            return $this->throwFailedAuthenticationException($request);
        }

        $this->guard->login($user, $request->boolean('remember'));

        return $next($request);
    }

    /**
     * Throw a failed authentication validation exception.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function throwFailedAuthenticationException($request)
    {
        $this->limiter->increment($request);

        throw ValidationException::withMessages([
            'credentials' => [trans('auth.failed')],
            'credentials_id' => [trans('auth.failed')],
        ]);
    }

    /**
     * Fire the failed authentication attempt event with the given arguments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function fireFailedEvent($request)
    {
        event(new Failed($this->guard?->name ?? config('fortify.guard'), null, [
            'credentials' => $request->credentials,
            'credentials_id' => $request->credentials_id,
        ]));
    }

}