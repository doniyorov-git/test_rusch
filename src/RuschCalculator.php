<?php

declare(strict_types=1);

namespace RuschBot;

final class RuschCalculator
{
    public const DEFAULT_MAX_BALL = 75.0;
    public const DEFAULT_QUESTION_COUNT = 45;

    public static function questionWeight(float $percentCorrect): float
    {
        self::validatePercent($percentCorrect);

        return 1 + (1 - $percentCorrect / 100);
    }

    public static function raschProbability(float $theta, float $difficulty): float
    {
        $exponent = $theta - $difficulty;

        if ($exponent >= 0) {
            return 1 / (1 + exp(-$exponent));
        }

        $value = exp($exponent);

        return $value / (1 + $value);
    }

    /**
     * @param array<int, array{question:int, percent_correct:float, correct_answer:string}> $questions
     * @param array<int, string> $studentAnswers
     * @return array{
     *     subject:string,
     *     correct_count:int,
     *     question_count:int,
     *     raw_score:float,
     *     max_score:float,
     *     ball_75:float,
     *     per_question:list<array{
     *         question:int,
     *         percent_correct:float,
     *         weight:float,
     *         correct_answer:string,
     *         student_answer:string,
     *         is_correct:bool,
     *         earned:float
     *     }>
     * }
     */
    public function calculateFromAnswers(
        string $subject,
        array $questions,
        array $studentAnswers,
        float $maxBall = self::DEFAULT_MAX_BALL,
        ?int $expectedQuestions = self::DEFAULT_QUESTION_COUNT
    ): array {
        $correctness = [];

        foreach ($questions as $questionNumber => $question) {
            $studentAnswer = strtoupper(trim($studentAnswers[$questionNumber] ?? ''));
            $correctAnswer = strtoupper(trim($question['correct_answer']));
            $correctness[$questionNumber] = $studentAnswer !== '' && $studentAnswer === $correctAnswer;
        }

        return $this->calculate($subject, $questions, $correctness, $studentAnswers, $maxBall, $expectedQuestions);
    }

    /**
     * @param array<int, array{question:int, percent_correct:float, correct_answer:string}> $questions
     * @param array<int, bool> $correctness
     * @return array<string, mixed>
     */
    public function calculateFromCorrectness(
        string $subject,
        array $questions,
        array $correctness,
        float $maxBall = self::DEFAULT_MAX_BALL,
        ?int $expectedQuestions = self::DEFAULT_QUESTION_COUNT
    ): array {
        return $this->calculate($subject, $questions, $correctness, [], $maxBall, $expectedQuestions);
    }

    /**
     * @param array<int, array{question:int, percent_correct:float, correct_answer:string}> $questions
     * @param array<int, bool> $correctness
     * @param array<int, string> $studentAnswers
     * @return array<string, mixed>
     */
    private function calculate(
        string $subject,
        array $questions,
        array $correctness,
        array $studentAnswers,
        float $maxBall,
        ?int $expectedQuestions
    ): array {
        $normalizedSubject = Subject::normalize($subject);

        if ($maxBall <= 0) {
            throw new \InvalidArgumentException("Max ball 0 dan katta bo'lishi kerak.");
        }

        ksort($questions);
        ksort($correctness);

        if ($questions === []) {
            throw new \InvalidArgumentException("Fan uchun savollar topilmadi: {$normalizedSubject}.");
        }

        if ($expectedQuestions !== null && count($questions) !== $expectedQuestions) {
            throw new \InvalidArgumentException(
                Subject::label($normalizedSubject) . " uchun {$expectedQuestions} ta savol kerak, "
                . count($questions) . ' ta topildi.'
            );
        }

        $missingAnswers = array_values(array_diff(array_keys($questions), array_keys($correctness)));
        $extraAnswers = array_values(array_diff(array_keys($correctness), array_keys($questions)));

        if ($missingAnswers !== []) {
            throw new \InvalidArgumentException('Javob yetishmagan savollar: ' . implode(', ', $missingAnswers) . '.');
        }

        if ($extraAnswers !== []) {
            throw new \InvalidArgumentException('Ortiqcha javoblar bor: ' . implode(', ', $extraAnswers) . '.');
        }

        $rawScore = 0.0;
        $maxScore = 0.0;
        $correctCount = 0;
        $perQuestion = [];

        foreach ($questions as $questionNumber => $question) {
            if ($questionNumber < 1) {
                throw new \InvalidArgumentException("Savol raqami 1 dan boshlanishi kerak.");
            }

            $percentCorrect = (float) $question['percent_correct'];
            $weight = self::questionWeight($percentCorrect);
            $isCorrect = (bool) $correctness[$questionNumber];
            $earned = $isCorrect ? $weight : 0.0;

            $maxScore += $weight;
            $rawScore += $earned;

            if ($isCorrect) {
                $correctCount++;
            }

            $perQuestion[] = [
                'question' => (int) $questionNumber,
                'percent_correct' => $percentCorrect,
                'weight' => $weight,
                'correct_answer' => strtoupper((string) $question['correct_answer']),
                'student_answer' => strtoupper((string) ($studentAnswers[$questionNumber] ?? '')),
                'is_correct' => $isCorrect,
                'earned' => $earned,
            ];
        }

        if ($maxScore <= 0) {
            throw new \InvalidArgumentException("Maksimal ball 0 dan katta bo'lishi kerak.");
        }

        return [
            'subject' => $normalizedSubject,
            'correct_count' => $correctCount,
            'question_count' => count($questions),
            'raw_score' => $rawScore,
            'max_score' => $maxScore,
            'ball_75' => $rawScore / $maxScore * $maxBall,
            'per_question' => $perQuestion,
        ];
    }

    private static function validatePercent(float $percentCorrect): void
    {
        if ($percentCorrect < 0 || $percentCorrect > 100) {
            throw new \InvalidArgumentException("Foiz 0 va 100 oralig'ida bo'lishi kerak.");
        }
    }
}
