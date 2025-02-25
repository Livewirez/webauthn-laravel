<?php

namespace Livewirez\Webauthn\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Livewirez\Webauthn\Passkey;
use Symfony\Component\Uid\Uuid;
use Livewirez\Webauthn\Webauthn;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Livewirez\Webauthn\Events\PasskeyRegistered;
use Livewirez\Webauthn\Events\PasskeyRegistrationFailed;

class PasskeyController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request, Webauthn $manager)
    {
        $credentials = $request->user()->passkeys()->get();
        $credentials = $credentials->map(static function (Passkey $source) {
            $data = $source->only(['id', 'name', 'public_key_credential_id', 'counter', 'aaguid', 'user_handle', 'backup_status', 'backup_eligible', 'usage_count']);
            $data['aaguid'] = Uuid::fromString($source->aaguid)->toRfc4122();
            $data['public_key_credential_id_hex'] = bin2hex($data['public_key_credential_id']);
            $data['last_used_at'] = $source->last_used_at ? (new \DateTimeImmutable($source->last_used_at))->format('j M Y, g:i a') : null;

            return (object) $data;
        });

        $key = Webauthn::PUBLIC_KEY_CREATION_OPTIONS_SESSION_KEY . ":{$request->user()->id}";

        $res = [
            'publicKeyCredentialCreationOptions' => (function () use ($key, $request, $manager) {
                if($request->session()->has($key)) {
                    $request->session()->forget($key);
                }

                $credentialOptions = $manager->getCredentialOptionsForUser($request->user(), true);

                $request->session()->put($key,  $credentialOptions);

               return $credentialOptions;
            })(),
            'credentials' => $credentials
        ];


        if ($request->expectsJson()) {
            return new JsonResponse($res);
        }

        return back()->with($res);
    }

    /**
     * Store a newly created passkey in storage.
     *
     * @param  Request  $request
     * @param  Webauthn  $manager
     * @return Response|JsonResponse|RedirectResponse
     * 
     * @throws ValidationException
     * @throws \Exception
     */
    public function store(Request $request, Webauthn $manager)
    {
        try {
            // Get stored options from session
            $sessionKey = Webauthn::PUBLIC_KEY_CREATION_OPTIONS_SESSION_KEY . ":{$request->user()->id}";
            $storedOptions = $request->session()->get($sessionKey);
            
            if (!$storedOptions) {
                throw ValidationException::withMessages(['passkey' => 'Registration session expired or invalid']);
            }

            // Deserialize and validate attestation
            $options = $manager->deserializePublicKeyCredentialCreationOptions($storedOptions);
            $publicKeyCredentialSource = $manager->validateAttestationResponse(
                $request->credentials, 
                $options
            );

            // Check for duplicate passkey
            $encodedCredentialId = Webauthn::encode($publicKeyCredentialSource->publicKeyCredentialId);
            $exists = $request->user()
                ->passkeys()
                ->where('public_key_credential_id', $encodedCredentialId)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['passkey' => 'This passkey has already been registered']);
            }

            // Create new passkey
            $passkey = $request->user()->passkeys()->create(
                array_merge(
                    Passkey::fromWebauthnSource($publicKeyCredentialSource),
                    ['name' => $request->input('name'), 'device_name' => $request->input('device_name')]
                )
            );

            // Clear the session data
            $request->session()->forget($sessionKey);

            // Return response based on request type
            $response = [
                'message' => 'Passkey registered successfully',
                'passkey' => $passkey->only(['id', 'name', 'created_at'])
            ];

            event(new PasskeyRegistered($request->user(), $passkey));

            return $request->expectsJson()
                ? new JsonResponse($response)
                : back()->with('success', $response['message']);

        } catch (ValidationException $e) {
            event(new PasskeyRegistrationFailed($request->user()));
            throw $e;
        } catch (\Throwable $th) {
            return $this->handleError($request, $th);
        }
    }

    /**
     * Handle general errors.
     *
     * @param Request $request
     * @param \Throwable $th
     * @return mixed
     */
    private function handleError(Request $request, \Throwable $th)
    {
        event(new PasskeyRegistrationFailed($request->user()));

        // Log the detailed error
        app(\Psr\Log\LoggerInterface::class)->error('Passkey registration failed', [
            'exception' => [
                'type' => get_class($th),
                'message' => $th->getMessage(),
                'code' => $th->getCode(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ],
            'user_id' => $request->user()->id,
            'method' => __METHOD__
        ]);

        $response = [
            'error' => config('app.debug')
                ? $th->getMessage()
                : 'An error occurred while registering the passkey'
        ];

        return $request->expectsJson()
            ? response()->json($response, 500)
            : back()->withErrors($response);
    }

    /**
     * Display the specified resource.
     */
    public function show(Passkey $passkey)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Passkey $passkey)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Passkey $passkey)
    {
        Gate::authorize('update', $passkey);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255']
        ]);

        $passkey->update($data);

        $res = [
            'message' => 'Passkey updated successfully',
            'passkey' => $passkey->only(['id', 'name', 'device_name', 'updated_at'])
        ];

        if ($request->expectsJson()) {
            return new JsonResponse($res);
        }

        return back()->with('success', $res['message']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Passkey $passkey)
    {
        Gate::authorize('delete', $passkey);

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $passkey->delete();

        $res = [
            'message' => 'Passkey deleted successfully'
        ];

        if ($request->expectsJson()) {
            return new JsonResponse($res);
        }

        return back()->with('success', $res['message']);
    }
}
