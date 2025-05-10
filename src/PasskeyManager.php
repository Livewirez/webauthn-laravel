<?php 

namespace Livewirez\Webauthn;

use Illuminate\Support\Facades\DB;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\Denormalizer\PublicKeyCredentialSourceDenormalizer;

trait PasskeyManager
{
    public static function migrate()
    {
        DB::transaction(function () {

            switch (config('database.default')) {
                case 'sqlite':
                case 'pgsql':
                    DB::statement(
                        <<<'SQL'
                            ALTER TABLE passkeys ADD COLUMN data JSON;
                        SQL
                    );
                    break;
                default:
                    break;
            }

            static::all()->each(function (Passkey $p) {
                $source = $p->transformToWebauthnSource();

                $p->data = $source;

                $p->save();
            });

            switch (config('database.default')) {
                case 'sqlite':

                    $passkeys = static::all();

                    DB::statement(
                        <<<'SQL'
                            DROP TABLE IF EXISTS passkeys;
                        SQL
                    );
              
                    DB::statement(
                        <<<'SQL'
                            CREATE TABLE passkeys (
                                id INTEGER PRIMARY KEY,
                                created_at DATETIME,
                                updated_at DATETIME,
                                
                                name VARCHAR(255),
                                device_name VARCHAR(255),
                                public_key_credential_id VARCHAR(255) UNIQUE,
                                data JSON,
                                trust_path_type VARCHAR(255),
                                last_used_at TIMESTAMP,
                                usage_count BIGINT DEFAULT 0,
                                
                                passkey_user_type VARCHAR(255),
                                passkey_user_id BIGINT
                            );
                        SQL
                    );
              
                    DB::statement(
                        <<<'SQL'
                            CREATE INDEX passkey_user_index ON passkeys (passkey_user_type, passkey_user_id);
                        SQL
                    );

                    $passkeys->each(fn (Passkey $p) => $p->create($p->toArray()));
                    break;
                case 'mysql':
                    break;
                case 'mariadb':
                    break;
                case 'pgsql':
                    DB::statement(
                        <<<'SQL'
                            ALTER TABLE passkeys
                            DROP COLUMN IF EXISTS credential_public_key,
                            DROP COLUMN IF EXISTS aaguid,
                            DROP COLUMN IF EXISTS type,
                            DROP COLUMN IF EXISTS transports,
                            DROP COLUMN IF EXISTS attestation_type,
                            DROP COLUMN IF EXISTS trust_path,
                            DROP COLUMN IF EXISTS user_handle,
                            DROP COLUMN IF EXISTS counter,
                            DROP COLUMN IF EXISTS other_ui,
                            DROP COLUMN IF EXISTS backup_eligible,
                            DROP COLUMN IF EXISTS backup_status,
                            DROP COLUMN IF EXISTS uv_initialized;
                        SQL
                    );
                    break;
                case 'sqlsrv':
                    break;
                default:
                    break;
            }
        });
    }

    public function getDenormalizedData(): array
    {
        return [
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
    }

    public function getDenormalizedValues(): PublicKeyCredentialSource
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
        $denormalizer->setDenormalizer($serializer = app()->make('webauthn_serializer')->getBaseSerializer());
        $denormalizer->setNormalizer($serializer);

        return $denormalizer;
    }
}