# The Quran's text

The platform's copy of the Quran, read by `Modules\Curriculum\Texts\QuranText`. Every word of the Quran a
screen or a helper shows comes from these two files, as they are: never typed, never generated, never edited.

| File | What it is | From |
|---|---|---|
| `quran-simple.txt` | Tanzil's **Simple** text, "Text (with aya numbers)": one `surah\|verse\|text` line a verse, Tanzil's notice at the end (kept: its terms ask for it) | https://tanzil.net/download |
| `quran-data.xml` | Tanzil's metadata: each surah's names and number of verses (and the pages, for the hifz map) | https://tanzil.net/docs/quran_metadata |

The server fetches both itself and keeps them in its storage (`storage/app/platform/quran`, with a
`manifest.json` of their SHA-256 and when they came), read before any copy here: `php artisan curriculum:quran
--fetch` (the deploy runs it with `--if-missing`), or the console's Platform settings › "The Quran's text".
They are kept only once all of them checks out (`Texts\QuranFiles::check`: 114 surahs, 6,236 verses, every
verse in order, in Arabic, without rub-el-hizb or sajdah signs, and Tanzil's notice). The addresses are
`QURAN_TEXT_URL` and `QURAN_DATA_URL` (`config/curriculum.php`).

Until a copy is in place, lessons cannot name a passage of the Quran (the builder says so), and everything
else works. A copy put here is held to the same checks by `tests/Feature/Curriculum/QuranFilesTest.php`.
