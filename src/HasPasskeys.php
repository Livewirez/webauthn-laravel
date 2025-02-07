<?php

namespace Livewirez\Webauthn;

trait HasPasskeys
{
    /**
     * Get the access tokens that belong to model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<Passkey, $this>
     */
    public function passkeys()
    {
        return $this->morphMany(Passkey::class, 'passkey_user');
    }
}