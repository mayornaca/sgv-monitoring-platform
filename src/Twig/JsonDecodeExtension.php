<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class JsonDecodeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('json_decode', [$this, 'jsonDecode']),
            new TwigFilter('preg_replace', [$this, 'pregReplace']),
        ];
    }

    public function jsonDecode(?string $json): ?array
    {
        if (empty($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    public function pregReplace(?string $subject, string $pattern, string $replacement): string
    {
        if (empty($subject)) {
            return '';
        }

        return preg_replace($pattern, $replacement, $subject) ?? $subject;
    }
}
