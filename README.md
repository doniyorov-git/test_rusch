# test_rusch

Tarix, biologiya va kimyo fanlari uchun 45 savolli baholash mezonlarini
RUSH/Rasch uslubidagi qiyinlik koeffitsiyenti orqali 75 ballik tizimga
o'tkazadigan kichik Python CLI.

## Formula

Har bir savol uchun qiyinlik koeffitsiyenti:

```text
K_i = 1 + (1 - P_i / 100)
```

Bu yerda:

- `P_i` - i-savolni to'g'ri ishlaganlar foizi
- `K_i` - savol koeffitsiyenti

O'quvchining xom bali:

```text
Raw = sum(K_i for every correct answer)
```

Maksimal mumkin bo'lgan ball:

```text
Max = sum(K_i for all 45 questions)
```

75 ballik yakuniy natija:

```text
Ball = Raw / Max * 75
```

Klassik Rasch ehtimollik formulasi ham yordamchi funksiya sifatida bor:

```text
P(theta) = e^(theta-b) / (1 + e^(theta-b))
```

## CSV formatlari

Savollar foizi fayli (`examples/question_percentages.csv`):

```csv
subject,question,percent_correct
tarix,1,48.77
tarix,2,72.40
```

O'quvchi javoblari fayli (`examples/student_answers.csv`):

```csv
student_id,subject,question,correct
ali,tarix,1,1
ali,tarix,2,0
```

`correct` ustuni uchun `1/0`, `true/false`, `ha/yo'q`, `togri/notogri`
qiymatlari qabul qilinadi.

## Ishlatish

```bash
python3 -m rusch_grading calculate \
  --questions examples/question_percentages.csv \
  --answers examples/student_answers.csv \
  --output results.csv
```

Natija:

```csv
student_id,subject,correct_count,question_count,raw_score,max_score,ball_75
ali,biologiya,30,45,44.1234,67.5678,48.98
```

Standart holatda har bir fan uchun 45 ta savol talab qilinadi. Sinov yoki
qisman hisoblash uchun:

```bash
python3 -m rusch_grading calculate \
  --questions small_questions.csv \
  --answers small_answers.csv \
  --allow-partial
```

## Testlar

```bash
python3 -m unittest discover -s tests
```