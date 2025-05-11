<?php

namespace Livewirez\Webauthn;

use Illuminate\Auth\Access\Response;

class PasskeyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(PasskeyAuthenticatable $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(PasskeyAuthenticatable $user, Passkey $passkey): bool
    {
        return $passkey->passkey_user()->is($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(PasskeyAuthenticatable $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(PasskeyAuthenticatable $user, Passkey $passkey): bool
    {
        return $passkey->passkey_user()->is($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(PasskeyAuthenticatable $user, Passkey $passkey): bool
    {
        return $passkey->passkey_user()->is($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(PasskeyAuthenticatable $user, Passkey $passkey): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(PasskeyAuthenticatable $user, Passkey $passkey): bool
    {
        return false;
    }
}
