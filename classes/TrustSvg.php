<?php

/** Strict, dependency-free SVG subset. No original XML nodes are copied to output. */
final class MarclandTrustSvg
{
    public const MAX_BYTES = 102400;

    public static function sanitize(string $xml): string
    {
        if ($xml === '' || strlen($xml) > self::MAX_BYTES
            || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new InvalidArgumentException('Invalid SVG size or XML declaration.');
        }
        if (!class_exists('DOMDocument')) {
            throw new RuntimeException('The PHP DOM extension is required for SVG uploads.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $source = new DOMDocument();
            $source->resolveExternals = false;
            $source->substituteEntities = false;
            $source->validateOnParse = false;
            if (!$source->loadXML($xml, LIBXML_NONET) || $source->doctype !== null) {
                throw new InvalidArgumentException('Invalid SVG XML.');
            }
            foreach ($source->childNodes as $node) {
                if ($node !== $source->documentElement && $node->nodeType !== XML_COMMENT_NODE) {
                    throw new InvalidArgumentException('Unsupported SVG document node.');
                }
            }
            $root = $source->documentElement;
            if (!$root || $root->localName !== 'svg') {
                throw new InvalidArgumentException('An SVG root is required.');
            }
            $output = new DOMDocument('1.0', 'UTF-8');
            $count = 0;
            $output->appendChild(self::copyElement($root, $output, new DOMXPath($source), 0, $count));
            $result = $output->saveXML($output->documentElement);
            if ($result === false || strlen($result) > self::MAX_BYTES) {
                throw new InvalidArgumentException('Sanitized SVG is too large.');
            }
            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function copyElement(DOMElement $element, DOMDocument $output, DOMXPath $xpath, int $depth, int &$count): DOMElement
    {
        $attributes = [
            'svg' => ['viewBox', 'width', 'height'],
            'g' => [],
            'path' => ['d'],
            'rect' => ['x', 'y', 'width', 'height', 'rx', 'ry'],
            'circle' => ['cx', 'cy', 'r'],
            'ellipse' => ['cx', 'cy', 'rx', 'ry'],
            'line' => ['x1', 'y1', 'x2', 'y2'],
            'polyline' => ['points'],
            'polygon' => ['points'],
            'title' => [],
            'desc' => [],
        ];
        $name = $element->localName;
        if (++$count > 2000 || $depth > 32 || !isset($attributes[$name])
            || ($depth > 0 && $name === 'svg') || $element->prefix
            || !in_array($element->namespaceURI, [null, '', 'http://www.w3.org/2000/svg'], true)) {
            throw new InvalidArgumentException('Unsupported SVG element or complexity.');
        }
        $target = $output->createElementNS('http://www.w3.org/2000/svg', $name);
        $presentation = ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'opacity', 'fill-opacity', 'stroke-opacity'];
        foreach ($element->attributes as $attribute) {
            $key = $attribute->nodeName;
            if ($attribute->namespaceURI || !in_array($key, array_merge($attributes[$name], $presentation), true)
                || !self::safeAttribute($key, $attribute->value)) {
                throw new InvalidArgumentException('Unsupported SVG attribute or value.');
            }
            $target->setAttribute($key, $attribute->value);
        }
        // DOM does not expose namespace declarations as ordinary attributes.
        foreach ($xpath->query('namespace::*', $element) as $namespace) {
            if (!in_array($namespace->nodeValue, ['http://www.w3.org/XML/1998/namespace', 'http://www.w3.org/2000/svg'], true)) {
                throw new InvalidArgumentException('Unsupported SVG namespace.');
            }
        }
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if (in_array($name, ['title', 'desc'], true)) {
                    throw new InvalidArgumentException('SVG descriptions must contain text only.');
                }
                $target->appendChild(self::copyElement($child, $output, $xpath, $depth + 1, $count));
            } elseif ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                if (in_array($name, ['title', 'desc'], true)) {
                    $target->appendChild($output->createTextNode($child->nodeValue));
                } elseif (trim($child->nodeValue) !== '') {
                    throw new InvalidArgumentException('Unsupported SVG text.');
                }
            } elseif ($child->nodeType !== XML_COMMENT_NODE) {
                throw new InvalidArgumentException('Unsupported SVG node.');
            }
        }
        return $target;
    }

    private static function safeAttribute(string $name, string $value): bool
    {
        if (in_array($name, ['fill', 'stroke'], true)) {
            return (bool) preg_match('/\A(?:none|currentColor|transparent|black|white|red|green|blue|gray|grey|#[a-fA-F0-9]{3}|#[a-fA-F0-9]{6}|#[a-fA-F0-9]{8})\z/D', $value);
        }
        $choices = [
            'stroke-linecap' => ['butt', 'round', 'square'],
            'stroke-linejoin' => ['miter', 'round', 'bevel'],
            'fill-rule' => ['nonzero', 'evenodd'],
        ];
        if (isset($choices[$name])) {
            return in_array($value, $choices[$name], true);
        }
        if ($name === 'd') {
            return (bool) preg_match('/\A[MmZzLlHhVvCcSsQqTtAa0-9eE+.,\s-]+\z/D', $value);
        }
        if ($name === 'points' || $name === 'viewBox') {
            $numbers = preg_split('/[\s,]+/', trim($value));
            if ($name === 'viewBox' && count($numbers) !== 4) {
                return false;
            }
            foreach ($numbers as $number) {
                if (!self::number($number)) {
                    return false;
                }
            }
            return count($numbers) >= 2;
        }
        if (!self::number($value)) {
            return false;
        }
        return !in_array($name, ['opacity', 'fill-opacity', 'stroke-opacity'], true)
            || ((float) $value >= 0 && (float) $value <= 1);
    }

    private static function number(string $value): bool
    {
        return (bool) preg_match('/\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/D', $value)
            && is_finite((float) $value) && abs((float) $value) <= 100000;
    }
}
