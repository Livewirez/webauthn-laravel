<?php

namespace Livewirez\Webauthn\Http\Controllers;

use Illuminate\View\View;

use Illuminate\Http\Request;
use Livewirez\Webauthn\Webauthn;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
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

        if ($request->expectsJson()) {
            return new JsonResponse([
                'redirect' => route('dashboard', absolute: false),
                'status' => 302
            ], 200);
        }

        return redirect()->intended(route('dashboard', absolute: false));
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
