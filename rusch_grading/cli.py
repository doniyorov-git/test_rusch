"""Command line interface for RUSH/Rasch-style assessment scoring."""

from __future__ import annotations

import argparse
import csv
import sys
from collections import defaultdict
from pathlib import Path
from typing import TextIO

from .calculator import GradingResult, calculate_score, normalize_subject


QUESTION_COLUMNS = ("question", "savol", "question_number")
PERCENT_COLUMNS = ("percent_correct", "percent", "p_i", "foiz", "togri_foiz")
SUBJECT_COLUMNS = ("subject", "fan")
STUDENT_COLUMNS = ("student_id", "student", "oquvchi", "o'quvchi", "id")
CORRECT_COLUMNS = ("correct", "is_correct", "togri", "to'g'ri", "javob")

TRUE_VALUES = {"1", "true", "t", "yes", "y", "ha", "togri", "to'g'ri", "correct"}
FALSE_VALUES = {"0", "false", "f", "no", "n", "yoq", "yo'q", "notogri", "noto'g'ri", "wrong"}


QuestionMap = dict[str, dict[int, float]]
AnswerMap = dict[str, dict[str, dict[int, bool]]]


def main(argv: list[str] | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)

    if args.command == "calculate":
        return calculate_command(args)

    parser.error("Unknown command.")
    return 2


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="rusch-grading",
        description="Calculate tarix, biologiya, and kimyo scores using the 75-point RUSH formula.",
    )
    subparsers = parser.add_subparsers(dest="command", required=True)

    calculate = subparsers.add_parser("calculate", help="Calculate scores from CSV input files.")
    calculate.add_argument(
        "--questions",
        required=True,
        type=Path,
        help="CSV with subject, question, and percent_correct columns.",
    )
    calculate.add_argument(
        "--answers",
        required=True,
        type=Path,
        help="CSV with student_id, subject, question, and correct columns.",
    )
    calculate.add_argument(
        "--output",
        type=Path,
        help="Optional output CSV path. Defaults to stdout.",
    )
    calculate.add_argument(
        "--max-ball",
        type=float,
        default=75,
        help="Final score scale. Defaults to 75.",
    )
    calculate.add_argument(
        "--allow-partial",
        action="store_true",
        help="Allow fewer than 45 questions per subject.",
    )

    return parser


def calculate_command(args: argparse.Namespace) -> int:
    try:
        questions = load_question_percentages(args.questions)
        answers = load_answers(args.answers)
        expected_questions = None if args.allow_partial else 45

        results = []
        for student_id in sorted(answers):
            for subject in sorted(answers[student_id]):
                if subject not in questions:
                    raise ValueError(f"No question percentages found for subject '{subject}'.")
                results.append(
                    calculate_score(
                        student_id=student_id,
                        subject=subject,
                        question_percentages=questions[subject],
                        answers=answers[student_id][subject],
                        max_ball=args.max_ball,
                        expected_questions=expected_questions,
                    )
                )

        if args.output:
            args.output.parent.mkdir(parents=True, exist_ok=True)
            with args.output.open("w", encoding="utf-8", newline="") as output_file:
                write_results(results, output_file)
        else:
            write_results(results, sys.stdout)

        return 0
    except (OSError, ValueError) as exc:
        print(f"Error: {exc}", file=sys.stderr)
        return 1


def load_question_percentages(path: Path) -> QuestionMap:
    rows = read_csv(path)
    questions: QuestionMap = defaultdict(dict)

    for line_number, row in rows:
        subject = normalize_subject(get_required(row, SUBJECT_COLUMNS, path, line_number))
        question = parse_int(get_required(row, QUESTION_COLUMNS, path, line_number), path, line_number)
        percent = parse_float(get_required(row, PERCENT_COLUMNS, path, line_number), path, line_number)

        if question in questions[subject]:
            raise ValueError(f"{path}:{line_number}: duplicate question {question} for {subject}.")

        questions[subject][question] = percent

    return dict(questions)


def load_answers(path: Path) -> AnswerMap:
    rows = read_csv(path)
    answers: AnswerMap = defaultdict(lambda: defaultdict(dict))

    for line_number, row in rows:
        student_id = get_optional(row, STUDENT_COLUMNS) or "student"
        subject = normalize_subject(get_required(row, SUBJECT_COLUMNS, path, line_number))
        question = parse_int(get_required(row, QUESTION_COLUMNS, path, line_number), path, line_number)
        correct = parse_bool(get_required(row, CORRECT_COLUMNS, path, line_number), path, line_number)

        if question in answers[student_id][subject]:
            raise ValueError(
                f"{path}:{line_number}: duplicate answer for {student_id}, {subject}, question {question}."
            )

        answers[student_id][subject][question] = correct

    return {student: dict(subjects) for student, subjects in answers.items()}


def read_csv(path: Path) -> list[tuple[int, dict[str, str]]]:
    with path.open("r", encoding="utf-8-sig", newline="") as input_file:
        reader = csv.DictReader(input_file)
        if reader.fieldnames is None:
            raise ValueError(f"{path}: CSV header is required.")

        rows = []
        for line_number, row in enumerate(reader, start=2):
            normalized_row = {
                (key or "").strip().lower(): (value or "").strip() for key, value in row.items()
            }
            if any(normalized_row.values()):
                rows.append((line_number, normalized_row))
        return rows


def get_required(
    row: dict[str, str],
    candidates: tuple[str, ...],
    path: Path,
    line_number: int,
) -> str:
    value = get_optional(row, candidates)
    if value is None:
        names = ", ".join(candidates)
        raise ValueError(f"{path}:{line_number}: one of these columns is required: {names}.")
    return value


def get_optional(row: dict[str, str], candidates: tuple[str, ...]) -> str | None:
    for candidate in candidates:
        value = row.get(candidate)
        if value:
            return value
    return None


def parse_int(value: str, path: Path, line_number: int) -> int:
    try:
        return int(value)
    except ValueError as exc:
        raise ValueError(f"{path}:{line_number}: '{value}' is not a valid integer.") from exc


def parse_float(value: str, path: Path, line_number: int) -> float:
    try:
        return float(value.replace(",", "."))
    except ValueError as exc:
        raise ValueError(f"{path}:{line_number}: '{value}' is not a valid number.") from exc


def parse_bool(value: str, path: Path, line_number: int) -> bool:
    normalized = value.strip().lower()
    if normalized in TRUE_VALUES:
        return True
    if normalized in FALSE_VALUES:
        return False
    raise ValueError(f"{path}:{line_number}: '{value}' is not a valid correct/incorrect value.")


def write_results(results: list[GradingResult], output_file: TextIO) -> None:
    writer = csv.DictWriter(
        output_file,
        fieldnames=[
            "student_id",
            "subject",
            "correct_count",
            "question_count",
            "raw_score",
            "max_score",
            "ball_75",
        ],
    )
    writer.writeheader()
    for result in results:
        writer.writerow(
            {
                "student_id": result.student_id,
                "subject": result.subject,
                "correct_count": result.correct_count,
                "question_count": result.question_count,
                "raw_score": f"{result.raw_score:.4f}",
                "max_score": f"{result.max_score:.4f}",
                "ball_75": f"{result.ball_75:.2f}",
            }
        )


if __name__ == "__main__":
    raise SystemExit(main())
