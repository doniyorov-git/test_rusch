import math
import unittest

from rusch_grading import calculate_score, question_weight, rasch_probability


class RuschCalculatorTest(unittest.TestCase):
    def test_question_weight_uses_percent_correct(self):
        self.assertAlmostEqual(question_weight(48.77), 1.5123)
        self.assertAlmostEqual(question_weight(59.11), 1.4089)

    def test_calculate_score_scales_raw_score_to_75_points(self):
        result = calculate_score(
            student_id="student-1",
            subject="tarix",
            question_percentages={1: 48.77, 2: 80.0, 3: 59.11},
            answers={1: True, 2: False, 3: True},
            expected_questions=None,
        )

        expected_raw = question_weight(48.77) + question_weight(59.11)
        expected_max = question_weight(48.77) + question_weight(80.0) + question_weight(59.11)
        self.assertEqual(result.correct_count, 2)
        self.assertAlmostEqual(result.raw_score, expected_raw)
        self.assertAlmostEqual(result.max_score, expected_max)
        self.assertAlmostEqual(result.ball_75, expected_raw / expected_max * 75)

    def test_supported_subject_aliases_are_normalized(self):
        percentages = {index: 50.0 for index in range(1, 46)}
        answers = {index: True for index in range(1, 46)}

        result = calculate_score(
            student_id="student-1",
            subject="chemistry",
            question_percentages=percentages,
            answers=answers,
        )

        self.assertEqual(result.subject, "kimyo")
        self.assertEqual(result.ball_75, 75)

    def test_default_assessment_requires_45_questions(self):
        with self.assertRaisesRegex(ValueError, "must have 45 questions"):
            calculate_score(
                student_id="student-1",
                subject="biologiya",
                question_percentages={1: 60.0},
                answers={1: True},
            )

    def test_rasch_probability_matches_logit_formula(self):
        self.assertAlmostEqual(rasch_probability(theta=0, difficulty=0), 0.5)
        self.assertAlmostEqual(
            rasch_probability(theta=2, difficulty=1),
            math.exp(1) / (1 + math.exp(1)),
        )


if __name__ == "__main__":
    unittest.main()
