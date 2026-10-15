# The Quran's text

The platform's copy of the Quran, read by `Modules\Curriculum\Texts\QuranText`. Every word of the Quran a
screen or a helper shows comes from these two files, as they are: never typed, never generated, never edited.

| File | What it is | From |
|---|---|---|
| `quran-simple.txt` | Tanzil's **Simple** text, "Text (with aya numbers)": one `surah\|verse\|text` line a verse, Tanzil's notice at the end (kept: its terms ask for it) | https://tanzil.net/download |
| `quran-data.xml` | Tanzil's metadata: each surah's names and number of verses (and the pages, for the hifz map) | https://tanzil.net/docs/quran_metadata |

Until both are here, lessons cannot name a passage of the Quran (the builder says so), and everything else
works. When they are added, `tests/Feature/Curriculum/QuranFilesTest.php` pins their checksums, 114 surahs
and 6,236 verses.
