<?php

namespace Livewirez\Webauthn;

use Illuminate\Contracts\Auth\Authenticatable;

interface PasskeyUserEntityInterface extends Authenticatable
{
    public function getName(): string;

    public function getDisplayName(): string;

    public function getId(): string;

    public function getIcon(): ?string;

    public function getPasskeys(): array;
}