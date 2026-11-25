<?php

namespace Modules\SharedKernel\Tenancy;

use Closure;
use Illuminate\Translation\Translator;

/**
 * Laravel's translator, with each academy's words for its audience: in an
 * academy that teaches adults only, `__('module::file.key')` (and
 * trans_choice, has, a whole group) first reads the module's
 * lang/{locale}/adults/{file}.php, which holds only the keys that read
 * differently there, and falls back to the base file key by key. A group
 * asked as a whole is the base with the variant's keys over it. JSON lines
 * and files without a namespace (validation, the app's lang/) are never
 * changed. Per language in turn: the adults line, then the base line, then
 * the fallback language's adults line, then its base line.
 *
 * Nothing is kept per academy: the translator already keeps every file it
 * loaded (a missing adults file is remembered as empty), and whom the
 * academy teaches is read from the tenant on each line (AcademyAudience,
 * scoped, so a worker or Octane moving to another academy reads the next
 * one's).
 */
final class AudienceTranslator extends Translator
{
    /** The folder beside each language's files that holds the adults academies' words. */
    public const FOLDER = 'adults';

    /** @var Closure(): bool whether the current academy teaches adults only */
    private Closure $adults;

    /**
     * The same translator (its loader, namespaces, languages, what it loaded
     * and its handlers) that reads the adults words when $adults says so.
     *
     * @param  Closure(): bool  $adults
     */
    public static function over(Translator $base, Closure $adults): self
    {
        $translator = new self($base->getLoader(), $base->getLocale());
        foreach (get_object_vars($base) as $property => $value) {
            $translator->{$property} = $value;
        }
        $translator->adults = $adults;

        return $translator;
    }

    protected function getLine($namespace, $group, $locale, $item, array $replace)
    {
        if ($namespace === '*' || $namespace === null || str_starts_with((string) $group, self::FOLDER.'/') || ! ($this->adults)()) {
            return parent::getLine($namespace, $group, $locale, $item, $replace);
        }

        $variant = parent::getLine($namespace, self::FOLDER.'/'.$group, $locale, $item, $replace);
        if ($variant === null) {
            return parent::getLine($namespace, $group, $locale, $item, $replace);
        }
        if (is_array($variant)) {
            $base = parent::getLine($namespace, $group, $locale, $item, $replace);

            return is_array($base) ? array_replace_recursive($base, $variant) : $variant;
        }

        return $variant;
    }
}
