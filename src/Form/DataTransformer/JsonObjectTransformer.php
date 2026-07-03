<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Textarea ↔ array for JSON objects (static route params, translations, responsive config).
 *
 * @implements DataTransformerInterface<string, array<string, mixed>>
 */
final class JsonObjectTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if (!\is_array($value) || [] === $value) {
            return '';
        }

        try {
            return (string) json_encode($value, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            return '';
        }
    }

    public function reverseTransform(mixed $value): array
    {
        if (!\is_string($value)) {
            return [];
        }

        $value = trim($value);
        if ('' === $value) {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TransformationFailedException('JSON inválido: '.$e->getMessage());
        }

        if (!\is_array($decoded)) {
            throw new TransformationFailedException('Se esperaba un objeto JSON (asociativo).');
        }

        /* @var array<string, mixed> $decoded */
        return $decoded;
    }
}
