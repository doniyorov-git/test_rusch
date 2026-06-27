<?php

declare(strict_types=1);

namespace RuschBot;

final class TelegramBot
{
    private const TELEGRAM_MESSAGE_LIMIT = 4096;

    public function __construct(
        private string $token,
        private QuestionBank $questionBank,
        private RuschCalculator $calculator = new RuschCalculator(),
        private string $apiBase = 'https://api.telegram.org'
    ) {
    }

    /**
     * @param array<string, mixed> $update
     * @return list<string>
     */
    public function handleUpdate(array $update): array
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if (!is_array($message) || !isset($message['chat']['id'])) {
            return [];
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim((string) ($message['text'] ?? ''));
        $replies = $this->buildReplies($text);

        foreach ($replies as $reply) {
            $this->sendMessage($chatId, $reply);
        }

        return $replies;
    }

    /**
     * @return list<string>
     */
    public function buildReplies(string $text): array
    {
        if (trim($text) === '') {
            return [$this->helpText()];
        }

        [$command, $payload] = $this->splitCommand($text);

        try {
            return match ($command) {
                '/start', '/help' => [$this->helpText()],
                '/fanlar', '/subjects' => [$this->subjectsText()],
                '/formula' => [$this->formulaText()],
                '/namuna', '/sample' => [$this->sampleText()],
                '/jadval', '/weights' => $this->chunkMessage($this->weightsText($payload)),
                '/ball', '/score' => $this->scoreFromAnswers($payload),
                '/rush', '/rasch' => $this->scoreFromCorrectness($payload),
                default => [$this->unknownCommandText()],
            };
        } catch (\Throwable $exception) {
            return ["Xatolik: " . $exception->getMessage() . "\n\n" . $this->shortUsageText()];
        }
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function splitCommand(string $text): array
    {
        $parts = preg_split('/\s+/', trim($text), 2);
        $command = strtolower($parts[0] ?? '');
        $command = preg_replace('/@.+$/', '', $command) ?? $command;

        return [$command, $parts[1] ?? ''];
    }

    /**
     * @return list<string>
     */
    private function scoreFromAnswers(string $payload): array
    {
        [$subject, $answersText] = $this->splitSubjectPayload($payload, '/ball tarix ABCDE...');
        $questions = $this->questionBank->questionsFor($subject);
        $answers = AnswerParser::parseLetters($answersText, count($questions));
        $result = $this->calculator->calculateFromAnswers($subject, $questions, $answers);

        return $this->chunkMessage($this->formatResult($result, true));
    }

    /**
     * @return list<string>
     */
    private function scoreFromCorrectness(string $payload): array
    {
        [$subject, $answersText] = $this->splitSubjectPayload($payload, '/rush tarix 101001...');
        $questions = $this->questionBank->questionsFor($subject);
        $correctness = AnswerParser::parseCorrectness($answersText, count($questions));
        $result = $this->calculator->calculateFromCorrectness($subject, $questions, $correctness);

        return $this->chunkMessage($this->formatResult($result, false));
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function splitSubjectPayload(string $payload, string $example): array
    {
        $parts = preg_split('/\s+/', trim($payload), 2);

        if (count($parts) < 2) {
            throw new \InvalidArgumentException("Fan va javoblarni yuboring. Masalan: {$example}");
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function formatResult(array $result, bool $showAnswerKey): string
    {
        $lines = [
            'Fan: ' . Subject::label((string) $result['subject']),
            "To'g'ri javoblar: {$result['correct_count']}/{$result['question_count']}",
            'Raw: ' . $this->formatNumber((float) $result['raw_score']) . ' / ' . $this->formatNumber((float) $result['max_score']),
            'Ball: ' . $this->formatNumber((float) $result['ball_75'], 2) . ' / 75',
            '',
            "Savollar bo'yicha:",
        ];

        foreach ($result['per_question'] as $row) {
            $status = $row['is_correct'] ? 'togri' : 'xato';
            $answerPart = $showAnswerKey
                ? " siz={$row['student_answer']} kalit={$row['correct_answer']}"
                : '';

            $lines[] = sprintf(
                "%02d)%s %s%% K=%s earned=%s %s",
                $row['question'],
                $answerPart,
                $this->formatNumber((float) $row['percent_correct'], 2),
                $this->formatNumber((float) $row['weight']),
                $this->formatNumber((float) $row['earned']),
                $status
            );
        }

        return implode("\n", $lines);
    }

    private function weightsText(string $payload): string
    {
        $subject = trim($payload);

        if ($subject === '') {
            throw new \InvalidArgumentException("Fan nomini yuboring. Masalan: /jadval kimyo");
        }

        $questions = $this->questionBank->questionsFor($subject);
        $lines = [
            Subject::label($subject) . " savol koeffitsiyentlari:",
            'Formula: K = 1 + (1 - P/100)',
            '',
        ];

        foreach ($questions as $question) {
            $weight = RuschCalculator::questionWeight((float) $question['percent_correct']);
            $lines[] = sprintf(
                "%02d) kalit=%s P=%s%% K=%s",
                $question['question'],
                $question['correct_answer'],
                $this->formatNumber((float) $question['percent_correct'], 2),
                $this->formatNumber($weight)
            );
        }

        return implode("\n", $lines);
    }

    private function subjectsText(): string
    {
        $labels = array_map(static fn (string $subject): string => Subject::label($subject), $this->questionBank->subjects());

        return "Fanlar:\n- " . implode("\n- ", $labels);
    }

    private function formulaText(): string
    {
        return implode("\n", [
            'RUSH/Rasch-style ball formulasi:',
            'K_i = 1 + (1 - P_i / 100)',
            'Raw = togri savollar K_i yigindisi',
            'Max = barcha savollar K_i yigindisi',
            'Ball = Raw / Max * 75',
            '',
            'Klassik Rasch ehtimollik yordamchi formulasi:',
            'P(theta) = e^(theta-b) / (1 + e^(theta-b))',
        ]);
    }

    private function sampleText(): string
    {
        return implode("\n", [
            'Namuna:',
            '/ball tarix ABCDEABCDEABCDEABCDEABCDEABCDEABCDEABCDEABCDE',
            '',
            "Agar sizda kalit bilan solishtirilgan 1/0 natija bo'lsa:",
            '/rush biologiya 101001101001101001101001101001101001101001101',
            '',
            "Savol vaznlari jadvali:",
            '/jadval kimyo',
        ]);
    }

    private function helpText(): string
    {
        return implode("\n", [
            'RUSH ball hisoblovchi bot.',
            '',
            'Buyruqlar:',
            '/ball <fan> <45 ta A-E javob> - kalit bilan tekshiradi va 75 ballga otkazadi',
            '/rush <fan> <45 ta 1/0> - tayyor togri/xato natijadan ball hisoblaydi',
            '/jadval <fan> - har savol foizi, kaliti va K koeffitsiyenti',
            '/fanlar - fanlar royxati',
            '/formula - hisoblash formulasi',
            '/namuna - namunalar',
            '',
            'Fanlar: tarix, biologiya, kimyo',
        ]);
    }

    private function shortUsageText(): string
    {
        return implode("\n", [
            'Ishlatish:',
            '/ball tarix ABCDEABCDEABCDEABCDEABCDEABCDEABCDEABCDEABCDE',
            '/rush kimyo 111000111000111000111000111000111000111000111',
        ]);
    }

    private function unknownCommandText(): string
    {
        return "Buyruq topilmadi.\n\n" . $this->shortUsageText();
    }

    /**
     * @return list<string>
     */
    private function chunkMessage(string $message): array
    {
        if (strlen($message) <= self::TELEGRAM_MESSAGE_LIMIT) {
            return [$message];
        }

        $chunks = [];
        $current = '';

        foreach (explode("\n", $message) as $line) {
            if (strlen($current) + strlen($line) + 1 > 3500) {
                $chunks[] = $current;
                $current = '';
            }

            $current .= ($current === '' ? '' : "\n") . $line;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function formatNumber(float $value, int $precision = 4): string
    {
        return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.');
    }

    private function sendMessage(string $chatId, string $text): void
    {
        if ($this->token === '') {
            return;
        }

        $payload = json_encode([
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ]);

        if ($payload === false) {
            throw new \RuntimeException("Telegram xabarini JSON qilishda xatolik.");
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 10,
            ],
        ]);

        $url = rtrim($this->apiBase, '/') . '/bot' . $this->token . '/sendMessage';
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException("Telegram API ga xabar yuborib bo'lmadi.");
        }
    }
}
