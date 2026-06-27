<?php

declare(strict_types=1);

namespace RuschBot;

final class QuestionBank
{
    /**
     * @param array<string, array<int, array{question:int, percent_correct:float, correct_answer:string}>> $questions
     */
    public function __construct(private array $questions)
    {
        foreach ($this->questions as $subject => $subjectQuestions) {
            Subject::normalize($subject);

            foreach ($subjectQuestions as $number => $question) {
                if ($number !== (int) $question['question']) {
                    throw new \InvalidArgumentException("Savol raqami mos emas: {$subject} #{$number}.");
                }

                RuschCalculator::questionWeight((float) $question['percent_correct']);
                self::normalizeAnswer((string) $question['correct_answer']);
            }
        }
    }

    public static function fromCsv(string $path): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Savol banki topilmadi: {$path}");
        }

        $file = new \SplFileObject($path, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $headers = null;
        $questions = [];

        foreach ($file as $rowNumber => $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $row = array_map(static fn ($value): string => trim((string) $value), $row);

            if ($headers === null) {
                $headers = $row;
                self::assertHeaders($headers, $path);
                continue;
            }

            if (count($row) < count($headers)) {
                throw new \InvalidArgumentException("CSV qatori to'liq emas: {$path}:" . ($rowNumber + 1));
            }

            $record = array_combine($headers, array_slice($row, 0, count($headers)));

            if ($record === false) {
                throw new \InvalidArgumentException("CSV qatorini o'qib bo'lmadi: {$path}:" . ($rowNumber + 1));
            }

            $subject = Subject::normalize($record['subject']);
            $questionNumber = filter_var($record['question'], FILTER_VALIDATE_INT);

            if ($questionNumber === false || $questionNumber < 1) {
                throw new \InvalidArgumentException("Savol raqami xato: {$path}:" . ($rowNumber + 1));
            }

            $percentCorrect = filter_var($record['percent_correct'], FILTER_VALIDATE_FLOAT);

            if ($percentCorrect === false) {
                throw new \InvalidArgumentException("Foiz xato: {$path}:" . ($rowNumber + 1));
            }

            $correctAnswer = self::normalizeAnswer($record['correct_answer']);

            if (isset($questions[$subject][$questionNumber])) {
                throw new \InvalidArgumentException("Takror savol: {$subject} #{$questionNumber}");
            }

            $questions[$subject][$questionNumber] = [
                'question' => $questionNumber,
                'percent_correct' => (float) $percentCorrect,
                'correct_answer' => $correctAnswer,
            ];
        }

        foreach ($questions as &$subjectQuestions) {
            ksort($subjectQuestions);
        }
        unset($subjectQuestions);

        return new self($questions);
    }

    /**
     * @return list<string>
     */
    public function subjects(): array
    {
        $subjects = array_keys($this->questions);
        sort($subjects);

        return $subjects;
    }

    /**
     * @return array<int, array{question:int, percent_correct:float, correct_answer:string}>
     */
    public function questionsFor(string $subject): array
    {
        $normalized = Subject::normalize($subject);

        if (!isset($this->questions[$normalized])) {
            throw new \InvalidArgumentException("Savol bankida fan topilmadi: " . Subject::label($normalized));
        }

        return $this->questions[$normalized];
    }

    public static function normalizeAnswer(string $answer): string
    {
        $answer = strtoupper(trim($answer));

        if (!preg_match('/^[A-E]$/', $answer)) {
            throw new \InvalidArgumentException("Javob A, B, C, D yoki E bo'lishi kerak: {$answer}");
        }

        return $answer;
    }

    /**
     * @param list<string> $headers
     */
    private static function assertHeaders(array $headers, string $path): void
    {
        $required = ['subject', 'question', 'percent_correct', 'correct_answer'];
        $missing = array_values(array_diff($required, $headers));

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                "{$path} CSV ustunlari yetishmaydi: " . implode(', ', $missing)
            );
        }
    }
}
