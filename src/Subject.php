<?php

declare(strict_types=1);

namespace RuschBot;

final class Subject
{
    private const ALIASES = [
        'tarix' => 'tarix',
        'history' => 'tarix',
        'biologiya' => 'biologiya',
        'biology' => 'biologiya',
        'bio' => 'biologiya',
        'kimyo' => 'kimyo',
        'chemistry' => 'kimyo',
        'chem' => 'kimyo',
    ];

    private const LABELS = [
        'tarix' => 'Tarix',
        'biologiya' => 'Biologiya',
        'kimyo' => 'Kimyo',
    ];

    public static function normalize(string $subject): string
    {
        $key = strtolower(trim($subject));

        if (!array_key_exists($key, self::ALIASES)) {
            throw new \InvalidArgumentException(
                "Noma'lum fan: {$subject}. Fanlar: " . implode(', ', self::labels())
            );
        }

        return self::ALIASES[$key];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_values(self::LABELS);
    }

    public static function label(string $subject): string
    {
        $normalized = self::normalize($subject);

        return self::LABELS[$normalized];
    }
}
