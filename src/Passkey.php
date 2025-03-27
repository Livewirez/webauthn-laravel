<?php

namespace Livewirez\Webauthn;

use Illuminate\Database\Eloquent\Model;
use Webauthn\PublicKeyCredentialSource;
use Symfony\Component\Serializer\SerializerInterface;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Webauthn\Denormalizer\PublicKeyCredentialSourceDenormalizer;

class Passkey extends Model
{
    protected $fillable = [
        'name',
        'device_name',
        'usage_count',
        'last_used_at',
        'public_key_credential_id',
        'credential_public_key',
        'aaguid', 
        'type',
        'transports',
        'attestation_type',
        'trust_path',
        'trust_path_type',
        'user_handle',
        'counter',
        'other_ui',
        'backup_eligible',
        'backup_status',
        'uv_initialized',
        'user_id',
    ];

    public function __construct(array $attributes = []) 
    {
        parent::__construct($attributes); 

        $this->setTable(config('test.tests_table')); 
    }

    protected function casts(): array
    {
        return [
            'transports' => 'json',
            'other_ui' => 'json',
            'trust_path' => 'json',
            'backup_eligible' => 'boolean',
            'backup_status' => 'boolean',
            'uv_initialized' => 'boolean',
        ];
    }

    public static function encode(string $string): string
    {
        return WebAuthn::encode($string);
    }

    public static function decode(string $string): string
    {
        return WebAuthn::decode($string);
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

    private function getDenormalizedValues(): PublicKeyCredentialSource
    {
        $data = [
            'publicKeyCredentialId' => $this->public_key_credential_id,
            'credentialPublicKey' => $this->credential_public_key,
            'aaguid' => $this->aaguid,
            'type' => $this->type,
            'transports' => $this->transports,
            'attestationType' => $this->attestation_type,
            'trustPath' => $this->trust_path,
            'userHandle' => $this->user_handle,
            'counter' => $this->counter,
            'otherUI' => $this->other_ui,
            'backupEligible' => $this->backup_eligible,
            'backupStatus' => $this->backup_status,
            'uvInitialized' => $this->uv_initialized,
        ];

        return static::getDemormalizer()->denormalize(
            $data, 
            PublicKeyCredentialSource::class
        );
    }
    
    private static function getNormalizedValues(PublicKeyCredentialSource $source)
    {
       $denormalizer = static::getDemormalizer();

       $data = $denormalizer->normalize($source);

        return [
            'public_key_credential_id' => $data['publicKeyCredentialId'],
            'credential_public_key' => $data['credentialPublicKey'],
            'aaguid' => $data['aaguid'], 
            'type' => $data['type'],
            'transports' => $data['transports'],
            'attestation_type' => $data['attestationType'],
            'trust_path' => $data['trustPath'],
            'trust_path_type' => get_class($source->trustPath),
            'user_handle' => $data['userHandle'],
            'counter' => $data['counter'],
            'other_ui' => $data['otherUI'] ?? null,
            'backup_eligible' => $data['backupEligible'] ?? null,
            'backup_status' => $data['backupStatus'] ?? null,
            'uv_initialized' => $data['uvInitialized'] ?? null,
        ];
    }

    public function getInsertQueryParams(): array
    {
        $data = [
            'public_key_credential_id' => $this->public_key_credential_id,
            'credential_public_key' => $this->credential_public_key,
            'aaguid' => $this->aaguid,
            'type' => $this->type,
            'transports' => $this->transports,
            'attestation_type' => $this->attestation_type,
            'trust_path' => $this->trust_path,
            'user_handle' => $this->user_handle,
            'counter' => $this->counter,
            'other_ui' => $this->other_ui,
            'backup_eligible' => !$this->backup_eligible ? 0 : 1,
            'backup_status' => !$this->backup_status ? 0 : 1,
            'uv_initialized' => !$this->uv_initialized ? 0 : 1,
            'trust_path_type' => $this->trust_path_type,
            'usage_count' => $this->usage_count ?? 0,
            'name' => $this->name,
            'device_name' => $this->device_name
        ];

        foreach($this->fillable as $value) {
            if(! array_key_exists($value, $data)) {
                $data[$value] = null;
            }
        }

        return $data;
    }

    public function getUpdateQueryParams(): array
    {
        return [
            'public_key_credential_id' => $this->public_key_credential_id,
            'credential_public_key' => $this->credential_public_key,
            'aaguid' => $this->aaguid,
            'type' => $this->type,
            'transports' => $this->transports,
            'attestation_type' => $this->attestation_type,
            'trust_path' => $this->trust_path,
            'user_handle' => $this->user_handle,
            'counter' => $this->counter,
            'other_ui' => $this->other_ui,
            'backup_eligible' => !$this->backup_eligible ? 0 : 1,
            'backup_status' => !$this->backup_status ? 0 : 1,
            'uv_initialized' => !$this->uv_initialized ? 0 : 1,
            'trust_path_type' => $this->trust_path_type,
            'usage_count' => $this->usage_count,
            'last_used_at' => $this->last_used_at,
            'name' => $this->name,
            'device_name' => $this->device_name
        ];
    }

    public static function fromWebauthnSource(PublicKeyCredentialSource $source, bool $return_model = false): static|array
    {
        $attributes = static::getNormalizedValues($source);

        return $return_model ? new static($attributes) : $attributes;
    }

    public function fillFromWebauthnSource(PublicKeyCredentialSource $source): static
    {
        $attributes = static::getNormalizedValues($source);

        return $this->fill($attributes);
    }

    public function transformToWebauthnSource(): PublicKeyCredentialSource
    {
        return $this->getDenormalizedValues();
    }

    public static function getDemormalizer(): PublicKeyCredentialSourceDenormalizer
    {
        $denormalizer = new PublicKeyCredentialSourceDenormalizer();
        $denormalizer->setDenormalizer($serializer = app()->make('webauthn_serializer'));
        $denormalizer->setNormalizer($serializer);

        return $denormalizer;
    }
}
