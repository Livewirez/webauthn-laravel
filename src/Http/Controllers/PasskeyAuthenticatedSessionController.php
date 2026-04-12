<?php

namespace Livewirez\Webauthn\Http\Controllers;

use Illuminate\View\View;

use Illuminate\Http\Request;
use Livewirez\Webauthn\Webauthn;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Livewirez\Webauthn\WebauthnConfig;
use Illuminate\Support\Facades\Redirect;
use \Symfony\Component\HttpFoundation\Response;
use Livewirez\Webauthn\Http\Requests\LoginRequest;


class PasskeyAuthenticatedSessionController 
{
    public function create(Request $request, Webauthn $webauthn): Response
    {
        $key = Webauthn::PUBLIC_KEY_REQUEST_OPTIONS_SESSION_KEY;

        $credentialRequestOptions = $webauthn->getAuthenticationOptions(null, true);
        $request->session()->put($key, $credentialRequestOptions);
     
        $res = ['publicKeyCredentialRequestOptions' => $credentialRequestOptions];

        if ($request->expectsJson()) {
            return new JsonResponse($res);
        }

        return Redirect::back()->with($res);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $url = null;

        if (isset(WebauthnConfig::$passkeyRedirectRouteResolver) && is_callable(WebauthnConfig::$passkeyRedirectRouteResolver)) {
            $url = call_user_func(WebauthnConfig::$passkeyRedirectRouteResolver);
        } else {
            $url = route(config('webauthn.login_redirect_route', 'dashboard'), absolute: false);
        }

        if ($request->expectsJson()) {
            return new JsonResponse([
                'redirect' => $url,
                'status' => 302
            ], 200);
        }

        return redirect()->intended($url);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
