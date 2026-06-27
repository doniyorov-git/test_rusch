#!/usr/bin/env php
<?php

declare(strict_types=1);

use RuschBot\AnswerParser;
use RuschBot\QuestionBank;
use RuschBot\RuschCalculator;
use RuschBot\Subject;

require dirname(__DIR__) . '/bootstrap.php';

$options = getopt('', ['subject:', 'answers::', 'correct::', 'bank::', 'help']);

if (isset($options['help']) || !isset($options['subject']) || (!isset($options['answers']) && !isset($options['correct']))) {
    fwrite(STDOUT, implode("\n", [
        'RUSH ball CLI',
        '',
        'Usage:',
        '  php bin/calculate.php --subject=tarix --answers=ABCDE...',
        '  php bin/calculate.php --subject=kimyo --correct=101001...',
        '',
        'Options:',
        '  --bank=/path/questions.csv   Default: data/questions.csv',
        '',
    ]));
    exit(isset($options['help']) ? 0 : 1);
}

$bankPath = (string) ($options['bank'] ?? dirname(__DIR__) . '/data/questions.csv');
$subject = (string) $options['subject'];
$questionBank = QuestionBank::fromCsv($bankPath);
$questions = $questionBank->questionsFor($subject);
$calculator = new RuschCalculator();

if (isset($options['answers'])) {
    $answers = AnswerParser::parseLetters((string) $options['answers'], count($questions));
    $result = $calculator->calculateFromAnswers($subject, $questions, $answers);
} else {
    $correctness = AnswerParser::parseCorrectness((string) $options['correct'], count($questions));
    $result = $calculator->calculateFromCorrectness($subject, $questions, $correctness);
}

fwrite(STDOUT, implode("\n", [
    'Fan: ' . Subject::label((string) $result['subject']),
    "Togri javoblar: {$result['correct_count']}/{$result['question_count']}",
    'Raw: ' . number_format((float) $result['raw_score'], 4, '.', '') . ' / ' . number_format((float) $result['max_score'], 4, '.', ''),
    'Ball: ' . number_format((float) $result['ball_75'], 2, '.', '') . ' / 75',
    '',
]));
