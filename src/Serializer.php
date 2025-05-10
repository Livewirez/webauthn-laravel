<?php

namespace Livewirez\Webauthn;

use Symfony\Component\Serializer\Encoder\JsonEncode;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

class Serializer implements SerializerInterface
{
    public SerializerInterface $symfonySerializer;

    public function __construct(
        protected WebauthnSerializerFactory $factory,
    ) {
        $this->symfonySerializer = $factory->create();
    }

    public static function make(): self
    {
        return new self(
            app(WebauthnSerializerFactory::class)
        );
    }

    public function getBaseSerializer(): SerializerInterface
    {
        return $this->symfonySerializer;
    }

    /**
     * Serializes data in the appropriate format.
     *
     * @param array<string, mixed> $context Options normalizers/encoders have access to
     */
    public function serialize(mixed $data, string $format, array $context = []): string
    {
        return $this->symfonySerializer->serialize($data, $format, $context);
    }

    /**
     * Deserializes data into the given type.
     *
     * @template TObject of object
     * @template TType of string|class-string<TObject>
     *
     * @param TType                $type
     * @param array<string, mixed> $context
     *
     * @psalm-return (TType is class-string<TObject> ? TObject : mixed)
     *
     * @phpstan-return ($type is class-string<TObject> ? TObject : mixed)
     */
    public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
        return $this->symfonySerializer->deserialize($data, $type, $format, $context);
    }

    public function toJson(mixed $value): string
    {
        return $this->serialize($value, 'json', [ // Optional
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            JsonEncode::OPTIONS => JSON_THROW_ON_ERROR,
        ]);
    }

    /**
     * @param  class-string  $desiredClass
     */
    public function fromJson(string $value, string $desiredClass): mixed
    {
        return $this
            ->symfonySerializer
            ->deserialize($value, $desiredClass, 'json');
    }
} 