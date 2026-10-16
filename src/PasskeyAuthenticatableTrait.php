<?php

namespace Livewirez\Webauthn;

use Illuminate\Contracts\Auth\Authenticatable;

trait PasskeyAuthenticatableTrait 
{
    use HasPasskeys;

    public function getName(): string 
    {
        return $this->name;
    }

    public function getDisplayName(): string 
    {
        return $this->name;
    }

    public function getId(): string 
    {
        return hash_hmac(
            'sha256',
            $this->getTable().'|'.$this->getKey(),
            config('passkeys.user_handle_secret'),
            binary: true,
        );
    }

    public function getIcon(): ?string 
    {
        return null;
    }

    /**
     * Get the users saved passkeys in an array
     * 
     * @return Passkey[]
     */
    public function getPasskeys(): array 
    {
        return $this->passkeys()->get()->all();
    }
}