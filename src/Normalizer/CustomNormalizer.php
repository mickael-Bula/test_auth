<?php

namespace App\Normalizer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CustomNormalizer implements NormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|float|\ArrayObject<string, mixed>|bool|int|string|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|float|\ArrayObject|int|bool|string|null
    {
        if (isset($object['createdAt']) && $object['createdAt'] instanceof \DateTime) {
            $object['createdAt'] = $object['createdAt']->format('d/m/Y');
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return is_array($data) && 'json' === $format && (isset($data['createdAt']));
    }

    /**
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return ['array' => true];
    }
}
