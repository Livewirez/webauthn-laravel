<?php

namespace Livewirez\Webauthn;

use Illuminate\Database\Eloquent\Model;
use Webauthn\PublicKeyCredentialSource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Serializer\SerializerInterface;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Webauthn\Denormalizer\PublicKeyCredentialSourceDenormalizer;

class Passkey extends Model
{
    use PasskeyManager;

    protected $fillable = [
        'name',
        'device_name',
        'usage_count',
        'last_used_at',
        'public_key_credential_id',
        'data',
        'user_id',
    ];

    public function __construct(array $attributes = []) 
    {
        parent::__construct($attributes); 

        $this->setTable(config('webauthn.passkeys_table')); 
    }

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'transports' => 'json',
            'other_ui' => 'json',
            'trust_path' => 'json',
            'backup_eligible' => 'boolean',
            'backup_status' => 'boolean',
            'uv_initialized' => 'boolean',
        ];
    }

    public function data(): Attribute
    {
        $serializer = app()->make('webauthn_serializer');

        return new Attribute(
            get: fn (string $value) => $serializer->fromJson(
                $value,
                PublicKeyCredentialSource::class
            ),
            set: fn (PublicKeyCredentialSource $value) => [
                'public_key_credential_id' => static::encode($value->publicKeyCredentialId),
                'trust_path_type' => get_class($value->trustPath),
                'data' => $serializer->toJson($value),
            ],
        );
    }

    public static function encode(string $string): string
    {
        return Webauthn::encode($string);
    }

    public static function decode(string $string): string
    {
        return Webauthn::decode($string);
    }

    /**
     * Get the passkey_user model that the access token belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function passkey_user(): MorphTo
    {
        return $this->morphTo('passkey_user');
    }
}
