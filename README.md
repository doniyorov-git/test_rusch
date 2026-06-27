# RUSH Telegram bot

Tarix, biologiya va kimyo fanlari uchun 45 savolli testlarni
RUSH/Rasch-style qiyinlik koeffitsiyenti orqali 75 ballik tizimga
o'tkazadigan PHP Telegram bot.

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

## Savol banki

Bot `data/questions.csv` faylidan foiz va javob kalitlarini o'qiydi.

```csv
subject,question,percent_correct,correct_answer
tarix,1,48.77,A
tarix,2,72.40,B
```

Qo'llab-quvvatlanadigan fanlar:

- `tarix`
- `biologiya`
- `kimyo`

Har bir fan uchun standart 45 ta savol talab qilinadi.

## Telegram botni ishga tushirish

1. Bot tokenini env orqali bering:

```bash
export TELEGRAM_BOT_TOKEN="123456:telegram-token"
```

2. PHP built-in server bilan lokal ishga tushirish:

```bash
php -S 0.0.0.0:8080 -t public
```

3. Webhookni serveringiz HTTPS URLiga ulang:

```bash
curl "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/setWebhook?url=https://example.com/index.php"
```

Agar savol banki boshqa joyda bo'lsa:

```bash
export QUESTION_BANK_CSV="/path/to/questions.csv"
```

## Telegram buyruqlari

```text
/start
/help
/fanlar
/formula
/jadval tarix
/ball tarix ABCDEABCDEABCDEABCDEABCDEABCDEABCDEABCDEABCDE
/rush biologiya 101001101001101001101001101001101001101001101
```

- `/ball` - foydalanuvchining 45 ta A-E javobini CSVdagi kalit bilan solishtiradi.
- `/rush` - oldindan tekshirilgan `1/0` natijalaridan ball hisoblaydi.
- `/jadval` - har savol uchun foiz, kalit va `K_i` koeffitsiyentini ko'rsatadi.

Bot natijada umumiy `Raw`, `Max`, `Ball / 75` va har bir savol bo'yicha
berilgan ballni chiqaradi.

## CLI orqali tekshirish

```bash
php bin/calculate.php --subject=tarix --answers=ABCDEABCDEABCDEABCDEABCDEABCDEABCDEABCDEABCDE
php bin/calculate.php --subject=kimyo --correct=111000111000111000111000111000111000111000111
```

## Testlar

```bash
php tests/run.php
```