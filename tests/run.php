<?php

declare(strict_types=1);

use RuschBot\AnswerParser;
use RuschBot\QuestionBank;
use RuschBot\RuschCalculator;
use RuschBot\TelegramBot;

require dirname(__DIR__) . '/bootstrap.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertAlmost(float $actual, float $expected, string $message, float $delta = 0.000001): void
{
    if (abs($actual - $expected) > $delta) {
        throw new RuntimeException($message . " Actual={$actual}, expected={$expected}");
    }
}

$calculator = new RuschCalculator();

assertAlmost(RuschCalculator::questionWeight(48.77), 1.5123, 'questionWeight(48.77)');
assertAlmost(RuschCalculator::questionWeight(59.11), 1.4089, 'questionWeight(59.11)');
assertAlmost(RuschCalculator::raschProbability(0, 0), 0.5, 'raschProbability midpoint');

$questions = [
    1 => ['question' => 1, 'percent_correct' => 48.77, 'correct_answer' => 'A'],
    2 => ['question' => 2, 'percent_correct' => 80.0, 'correct_answer' => 'B'],
    3 => ['question' => 3, 'percent_correct' => 59.11, 'correct_answer' => 'C'],
];

$result = $calculator->calculateFromAnswers(
    'tarix',
    $questions,
    [1 => 'A', 2 => 'C', 3 => 'C'],
    expectedQuestions: null
);

$expectedRaw = RuschCalculator::questionWeight(48.77) + RuschCalculator::questionWeight(59.11);
$expectedMax = RuschCalculator::questionWeight(48.77)
    + RuschCalculator::questionWeight(80.0)
    + RuschCalculator::questionWeight(59.11);

assertTrue($result['correct_count'] === 2, 'correct_count');
assertAlmost((float) $result['raw_score'], $expectedRaw, 'raw_score');
assertAlmost((float) $result['max_score'], $expectedMax, 'max_score');
assertAlmost((float) $result['ball_75'], $expectedRaw / $expectedMax * 75, 'ball_75');

$letters = AnswerParser::parseLetters('1:A 2:B 3:C', 3);
assertTrue($letters === [1 => 'A', 2 => 'B', 3 => 'C'], 'numbered letter parser');

$correctness = AnswerParser::parseCorrectness('101', 3);
assertTrue($correctness === [1 => true, 2 => false, 3 => true], 'correctness parser');

$bank = QuestionBank::fromCsv(dirname(__DIR__) . '/data/questions.csv');
assertTrue(count($bank->questionsFor('tarix')) === 45, 'tarix has 45 questions');
assertTrue(count($bank->questionsFor('biologiya')) === 45, 'biologiya has 45 questions');
assertTrue(count($bank->questionsFor('kimyo')) === 45, 'kimyo has 45 questions');

$bot = new TelegramBot('', $bank);
$replies = $bot->buildReplies('/rush tarix 111111111111111111111111111111111111111111111');
assertTrue(count($replies) >= 1, 'bot returns reply');
assertTrue(str_contains($replies[0], 'Ball: 75 / 75'), 'all correct gives 75');

fwrite(STDOUT, "All tests passed.\n");
