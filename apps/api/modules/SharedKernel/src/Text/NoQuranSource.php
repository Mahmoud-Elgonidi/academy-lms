<?php

namespace Modules\SharedKernel\Text;

/** No module keeps the Quran's text: nothing to show or fetch. */
final class NoQuranSource implements QuranSource
{
    public function status(): array
    {
        return ['available' => false, 'where' => null, 'notice' => null, 'verses' => null, 'text_sha256' => null, 'data_sha256' => null, 'fetched_at' => null];
    }

    public function fetch(): array
    {
        throw new QuranFetchFailed('No module keeps the Quran\'s text.');
    }
}
