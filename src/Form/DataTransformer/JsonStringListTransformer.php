<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Textarea ↔ list<string> (dynamic route param keys), JSON array e.g. ["id","slug"].
 *
 * @implements DataTransformerInterface<array<int|string, mixed>|null, string>
 */
final class JsonStringListTransformer implements DataTransformerInterface
{
    /**
     * @param mixed $value Model data from the form (list of strings in normal use)
     */
    public function transform(mixed $value): string
    {
        if (!\is_array($value) || [] === $value) {
            return '';
        }

        $strings = [];
        foreach ($value as $item) {
            if (\is_string($item) && '' !== $item) {
                $strings[] = $item;
            }
        }

        try {
            return (string) json_encode($strings, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            return '';
        }
    }

    /**
     * @param mixed $value View data from the textarea
     *
     * @return list<string>|null
     */
    public function reverseTransform(mixed $value): ?array
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TransformationFailedException('JSON inválido: '.$e->getMessage());
        }

        if (!\is_array($decoded)) {
            throw new TransformationFailedException('Se esperaba un array JSON de cadenas.');
        }

        $out = [];
        foreach ($decoded as $item) {
            if (\is_string($item) && '' !== $item) {
                $out[] = $item;
            }
        }

        return [] === $out ? null : $out;
    }
}
