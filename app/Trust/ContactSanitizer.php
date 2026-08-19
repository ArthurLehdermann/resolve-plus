<?php

namespace App\Trust;

use App\Trust\Enums\PadraoContatoDetectado;

final class ContactSanitizer
{
    private const REPLACEMENT = '[contato removido]';

    /**
     * @return array{sanitized: string, detected_patterns: list<PadraoContatoDetectado>, changed: bool}
     */
    public function sanitize(string $text): array
    {
        $sanitized = $text;
        $detected = [];

        $patterns = [
            [
                'kind' => PadraoContatoDetectado::Telefone,
                'regex' => '/(?:(?:\+?55\s*)?(?:\(?\d{2}\)?\s*)?(?:9\s*)?\d(?:[\s\-.]?\d){7,})|(?:whats(?:app)?|zap)/iu',
            ],
            [
                'kind' => PadraoContatoDetectado::Email,
                'regex' => '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|(?:\b[\pL\pN._%+\-]+\s+arroba\s+[\pL\pN.\-]+\s+ponto\s+\w+\b)/iu',
            ],
            [
                'kind' => PadraoContatoDetectado::RedeSocial,
                'regex' => '/(?:^|\s)@\w{2,}|\b(?:instagram|insta|telegram|telegr[ae]m)\b/iu',
            ],
        ];

        foreach ($patterns as $pattern) {
            $newText = preg_replace($pattern['regex'], ' '.self::REPLACEMENT, $sanitized);

            if ($newText !== null && $newText !== $sanitized) {
                $sanitized = trim($newText);
                $detected[] = $pattern['kind'];
            }
        }

        return [
            'sanitized' => $sanitized,
            'detected_patterns' => $detected,
            'changed' => $sanitized !== $text,
        ];
    }
}
