<?php

namespace Livewirez\Webauthn;

trait HasPasskeys
{
    /**
     * Get the passkeys that belong to model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<Passkey, $this>
     */
    public function passkeys()
    {
        return $this->morphMany(Passkey::class, 'passkey_user');
    }
}