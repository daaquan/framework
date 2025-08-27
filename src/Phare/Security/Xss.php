<?php

namespace Phare\Security;

class Xss
{
    protected static array $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre',
        'a', 'img', 'div', 'span'
    ];

    protected static array $allowedAttributes = [
        'href', 'src', 'alt', 'title', 'class', 'id'
    ];

    protected static array $dangerousPatterns = [
        '/javascript:/i',
        '/vbscript:/i',
        '/on\w+\s*=/i',
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
        '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi',
        '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/mi',
        '/<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>/mi'
    ];

    public static function clean(string $input, array $allowedTags = null, array $allowedAttributes = null): string
    {
        $allowedTags = $allowedTags ?? static::$allowedTags;
        $allowedAttributes = $allowedAttributes ?? static::$allowedAttributes;

        // Remove dangerous patterns
        $input = static::removeDangerousPatterns($input);

        // Strip all tags except allowed ones
        $input = strip_tags($input, '<' . implode('><', $allowedTags) . '>');

        // Clean attributes
        $input = static::cleanAttributes($input, $allowedAttributes);

        return $input;
    }

    public static function escape(string $input, int $flags = ENT_QUOTES, string $encoding = 'UTF-8'): string
    {
        return htmlspecialchars($input, $flags, $encoding);
    }

    public static function stripTags(string $input, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            return strip_tags($input);
        }

        return strip_tags($input, '<' . implode('><', $allowedTags) . '>');
    }

    public static function removeDangerousPatterns(string $input): string
    {
        foreach (static::$dangerousPatterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }

        return $input;
    }

    public static function cleanAttributes(string $input, array $allowedAttributes): string
    {
        try {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($input, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);
            $elements = $xpath->query('//*[@*]');

            foreach ($elements as $element) {
                $attributes = [];
                foreach ($element->attributes as $attribute) {
                    $attributes[] = $attribute->name;
                }

                foreach ($attributes as $attributeName) {
                    if (!in_array($attributeName, $allowedAttributes)) {
                        $element->removeAttribute($attributeName);
                    }
                }
            }

            return $dom->saveHTML();
        } catch (\Exception $e) {
            // Fallback to regex-based attribute cleaning
            return preg_replace_callback('/<([^>]+)>/', function ($matches) use ($allowedAttributes) {
                $tag = $matches[1];
                $cleanTag = preg_replace('/\s+(\w+)=("[^"]*"|\'[^\']*\'|[^\s>]+)/', function ($attrMatches) use ($allowedAttributes) {
                    $attr = $attrMatches[1];
                    return in_array($attr, $allowedAttributes) ? $attrMatches[0] : '';
                }, $tag);
                return '<' . $cleanTag . '>';
            }, $input);
        }
    }

    public static function filterInput(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Remove control characters except tab, newline, and carriage return
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);

        return $input;
    }

    public static function sanitizeUrl(string $url): string
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parsed = parse_url($url);
        if (!$parsed || in_array($parsed['scheme'] ?? '', ['javascript', 'vbscript', 'data'])) {
            return '';
        }

        return $url;
    }

    public static function validateEmail(string $email): string
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) ?: '';
    }

    public static function isBase64(string $input): bool
    {
        return base64_encode(base64_decode($input, true)) === $input;
    }

    public static function removeBase64Images(string $input): string
    {
        return preg_replace('/data:image\/[^;]+;base64[^"]+/i', '', $input);
    }

    public static function setAllowedTags(array $tags): void
    {
        static::$allowedTags = $tags;
    }

    public static function setAllowedAttributes(array $attributes): void
    {
        static::$allowedAttributes = $attributes;
    }

    public static function addAllowedTag(string $tag): void
    {
        if (!in_array($tag, static::$allowedTags)) {
            static::$allowedTags[] = $tag;
        }
    }

    public static function addAllowedAttribute(string $attribute): void
    {
        if (!in_array($attribute, static::$allowedAttributes)) {
            static::$allowedAttributes[] = $attribute;
        }
    }
}