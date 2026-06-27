"""Core scoring logic for 75-point RUSH/Rasch-style assessments.

The implemented scoring formula follows the user-facing rule:

    K_i = 1 + (1 - P_i / 100)
    Raw = sum(K_i for every correctly answered question)
    Ball = Raw / Max * 75

where P_i is the percentage of learners who answered question i correctly.
"""

from __future__ import annotations

from dataclasses import dataclass
from math import exp
from typing import Mapping


SUPPORTED_SUBJECTS = {"tarix", "biologiya", "kimyo"}

SUBJECT_ALIASES = {
    "history": "tarix",
    "tarix": "tarix",
    "biologiya": "biologiya",
    "biology": "biologiya",
    "kimyo": "kimyo",
    "chemistry": "kimyo",
    "chem": "kimyo",
}


@dataclass(frozen=True)
class QuestionDifficulty:
    """Difficulty data for a single assessment question."""

    subject: str
    question: int
    percent_correct: float

    def __post_init__(self) -> None:
        normalized_subject = normalize_subject(self.subject)
        object.__setattr__(self, "subject", normalized_subject)

        if self.question < 1:
            raise ValueError("Question number must be 1 or greater.")
        validate_percent(self.percent_correct)

    @property
    def weight(self) -> float:
        return question_weight(self.percent_correct)


@dataclass(frozen=True)
class GradingResult:
    """Calculated assessment result for one student and subject."""

    student_id: str
    subject: str
    correct_count: int
    question_count: int
    raw_score: float
    max_score: float
    ball_75: float


def normalize_subject(subject: str) -> str:
    """Return the canonical Uzbek subject name."""

    normalized = subject.strip().lower()
    try:
        return SUBJECT_ALIASES[normalized]
    except KeyError as exc:
        supported = ", ".join(sorted(SUPPORTED_SUBJECTS))
        raise ValueError(f"Unsupported subject '{subject}'. Supported: {supported}.") from exc


def validate_percent(percent_correct: float) -> None:
    if percent_correct < 0 or percent_correct > 100:
        raise ValueError("Percent correct must be between 0 and 100.")


def question_weight(percent_correct: float) -> float:
    """Calculate K_i = 1 + (1 - P_i / 100)."""

    validate_percent(percent_correct)
    return 1 + (1 - percent_correct / 100)


def rasch_probability(theta: float, difficulty: float) -> float:
    """Return P(theta) = e^(theta-b) / (1 + e^(theta-b)).

    This helper is provided for users who also need the classical Rasch
    probability curve, while the 75-point score uses ``question_weight``.
    """

    exponent = theta - difficulty
    if exponent >= 0:
        denominator = 1 + exp(-exponent)
        return 1 / denominator
    value = exp(exponent)
    return value / (1 + value)


def calculate_score(
    *,
    student_id: str,
    subject: str,
    question_percentages: Mapping[int, float],
    answers: Mapping[int, bool],
    max_ball: float = 75,
    expected_questions: int | None = 45,
) -> GradingResult:
    """Calculate the final 75-point score for one student and subject.

    Args:
        student_id: Student identifier used in output.
        subject: One of tarix, biologiya, kimyo, or their English aliases.
        question_percentages: Mapping of question number to percent-correct.
        answers: Mapping of question number to True/False correctness.
        max_ball: Final score scale, 75 by default.
        expected_questions: Require this many questions when provided. The
            requested assessment format uses 45 questions per subject.
    """

    canonical_subject = normalize_subject(subject)

    if max_ball <= 0:
        raise ValueError("max_ball must be greater than 0.")
    if not question_percentages:
        raise ValueError("At least one question percentage is required.")
    if expected_questions is not None and len(question_percentages) != expected_questions:
        raise ValueError(
            f"{canonical_subject} must have {expected_questions} questions; "
            f"got {len(question_percentages)}."
        )

    missing_answers = sorted(set(question_percentages) - set(answers))
    extra_answers = sorted(set(answers) - set(question_percentages))
    if missing_answers:
        raise ValueError(f"Missing answers for questions: {missing_answers}.")
    if extra_answers:
        raise ValueError(f"Answers contain unknown questions: {extra_answers}.")

    max_score = 0.0
    raw_score = 0.0
    correct_count = 0

    for question in sorted(question_percentages):
        if question < 1:
            raise ValueError("Question number must be 1 or greater.")

        weight = question_weight(float(question_percentages[question]))
        max_score += weight

        if bool(answers[question]):
            raw_score += weight
            correct_count += 1

    if max_score <= 0:
        raise ValueError("Calculated maximum score must be greater than 0.")

    ball_75 = raw_score / max_score * max_ball
    return GradingResult(
        student_id=student_id,
        subject=canonical_subject,
        correct_count=correct_count,
        question_count=len(question_percentages),
        raw_score=raw_score,
        max_score=max_score,
        ball_75=ball_75,
    )
