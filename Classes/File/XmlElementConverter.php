<?php

declare(strict_types=1);

/*
 * This file is part of the package cpsit/import-export-core.
 *
 * It is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License, either version 2 of the License, or (at
 * your option) any later version.
 *
 * For the full license information, please read the LICENSE file that was
 * distributed with this source code.
 */

namespace CPSIT\ImportExportCore\File;

/**
 * Converts an XML element into an array.
 *
 * - Attributes are collected in `@attributes` (name => value).
 * - Child elements are fields, named by their local name. Repeated elements
 *   become a list. An element with only text becomes a string, an empty
 *   element ''.
 * - Text of an element that also has attributes or child elements is kept
 *   in `@value`, if it is not only whitespace.
 *
 * Namespace prefixes are not part of the names.
 *
 * @internal
 */
final class XmlElementConverter
{
    public const string KEY_ATTRIBUTES = '@attributes';
    public const string KEY_VALUE = '@value';

    /**
     * @return array<string, mixed>
     */
    public function toRecord(\DOMElement $element): array
    {
        $value = $this->convert($element);

        return \is_array($value) ? $value : ($value === '' ? [] : [self::KEY_VALUE => $value]);
    }

    /**
     * @return array<string, mixed>|string
     */
    private function convert(\DOMElement $element): array|string
    {
        $result = [];
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            /** @var \DOMAttr $attribute */
            $attributes[$attribute->localName ?? $attribute->name] = $attribute->value;
        }
        if ($attributes !== []) {
            $result[self::KEY_ATTRIBUTES] = $attributes;
        }

        $text = '';
        $hasChildElements = false;
        $repeated = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $hasChildElements = true;
                $name = (string)($child->localName ?? $child->nodeName);
                $value = $this->convert($child);
                if (!\array_key_exists($name, $result)) {
                    $result[$name] = $value;
                } elseif (isset($repeated[$name]) && \is_array($result[$name])) {
                    $result[$name][] = $value;
                } else {
                    $result[$name] = [$result[$name], $value];
                    $repeated[$name] = true;
                }
            } elseif ($child instanceof \DOMText) {
                // includes CDATA sections
                $text .= $child->data;
            }
        }

        if (!$hasChildElements && $attributes === []) {
            return $text;
        }
        if (trim($text) !== '') {
            $result[self::KEY_VALUE] = trim($text);
        }

        return $result;
    }
}
