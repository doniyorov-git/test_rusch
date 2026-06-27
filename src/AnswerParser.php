<?php

declare(strict_types=1);

namespace RuschBot;

final class AnswerParser
{
    /**
     * @return array<int, string>
     */
    public static function parseLetters(string $input, int $questionCount): array
    {
        $input = trim($input);

        if ($input === '') {
            throw new \InvalidArgumentException("Javoblar bo'sh bo'lmasligi kerak.");
        }

        $numbered = self::parseNumberedLetters($input);

        if ($numbered !== []) {
            self::assertExactQuestionSet($numbered, $questionCount);

            return $numbered;
        }

        $compact = strtoupper(preg_replace('/[^A-E]/i', '', $input) ?? '');

        if (strlen($compact) !== $questionCount) {
            throw new \InvalidArgumentException(
                "{$questionCount} ta javob yuboring. Masalan: /ball tarix ABCDE..."
            );
        }

        $answers = [];

        for ($index = 0; $index < $questionCount; $index++) {
            $answers[$index + 1] = $compact[$index];
        }

        return $answers;
    }

    /**
     * @return array<int, bool>
     */
    public static function parseCorrectness(string $input, int $questionCount): array
    {
        $input = trim($input);

        if ($input === '') {
            throw new \InvalidArgumentException("Natijalar bo'sh bo'lmasligi kerak.");
        }

        $numbered = self::parseNumberedCorrectness($input);

        if ($numbered !== []) {
            self::assertExactQuestionSet($numbered, $questionCount);

            return $numbered;
        }

        $compact = preg_replace('/[^01]/', '', $input) ?? '';

        if (strlen($compact) !== $questionCount) {
            throw new \InvalidArgumentException(
                "{$questionCount} ta 1/0 qiymat yuboring. Masalan: /rush biologiya 101001..."
            );
        }

        $answers = [];

        for ($index = 0; $index < $questionCount; $index++) {
            $answers[$index + 1] = $compact[$index] === '1';
        }

        return $answers;
    }

    /**
     * @return array<int, string>
     */
    private static function parseNumberedLetters(string $input): array
    {
        preg_match_all('/\b(\d{1,2})\s*[:=.)-]\s*([A-E])\b/i', $input, $matches, PREG_SET_ORDER);

        $answers = [];

        foreach ($matches as $match) {
            $answers[(int) $match[1]] = strtoupper($match[2]);
        }

        return $answers;
    }

    /**
     * @return array<int, bool>
     */
    private static function parseNumberedCorrectness(string $input): array
    {
        preg_match_all('/\b(\d{1,2})\s*[:=.)-]\s*([01])\b/i', $input, $matches, PREG_SET_ORDER);

        $answers = [];

        foreach ($matches as $match) {
            $answers[(int) $match[1]] = $match[2] === '1';
        }

        return $answers;
    }

    /**
     * @param array<int, mixed> $answers
     */
    private static function assertExactQuestionSet(array $answers, int $questionCount): void
    {
        $expected = range(1, $questionCount);
        $actual = array_keys($answers);
        sort($actual);

        if ($actual !== $expected) {
            throw new \InvalidArgumentException("1 dan {$questionCount} gacha barcha savollar uchun javob yuboring.");
        }
    }
}
