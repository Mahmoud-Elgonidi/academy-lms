import { getLocale, getTranslations } from 'next-intl/server';
import { get } from '@/lib/api/server';
import { startHref, type SocialLink } from '@/lib/social';
import { webProviders } from '@/lib/social-server';
import { Badge, Card, Empty } from '@/components/ui';
import { UnlinkSocial } from './unlink-social';

/**
 * Google and Apple on this account: linked as which email (and last used),
 * or a way to link it (through the provider, back to `back`), and unlinking
 * (not when it is the only way in; the API says so).
 */
export async function SocialLinks({ back }: { back: string }) {
  const [t, locale, mine, providers] = await Promise.all([getTranslations('account'), getLocale(), get<{ data: SocialLink[] }>('/v1/me/social'), webProviders()]);
  const web = new Set(providers.map((p) => p.provider));
  const rows = mine.data.filter((row) => web.has(row.provider));

  return (
    <Card>
      <h2 className="font-heading text-lg font-bold">{t('socialTitle')}</h2>
      <p className="mt-1 text-sm text-muted">{t('socialHint')}</p>
      {rows.length === 0 ? (
        <div className="mt-4">
          <Empty>{t('none')}</Empty>
        </div>
      ) : (
        <ul className="mt-4 divide-y divide-line">
          {rows.map((row) => (
            <li key={row.provider} data-provider={row.provider} className="flex flex-wrap items-center justify-between gap-3 py-3">
              <div className="min-w-0">
                <p className="flex items-center gap-2 font-semibold">
                  {row.label}
                  <Badge tone={row.linked ? 'brand' : 'muted'}>{row.linked ? t('linked') : t('notLinked')}</Badge>
                </p>
                {row.linked ? (
                  <p className="text-sm break-words text-muted">
                    {[row.email, row.last_used_at ? t('lastUsed', { date: row.last_used_at.label }) : null].filter(Boolean).join(' · ')}
                  </p>
                ) : null}
              </div>
              {row.linked ? (
                <UnlinkSocial provider={row.provider} label={row.label} />
              ) : (
                <a
                  href={startHref(row.provider, locale, { link: true, back })}
                  className="inline-flex min-h-10 items-center rounded-control border border-line bg-white px-4 text-sm font-semibold shadow-sm hover:bg-paper"
                >
                  {t('link', { provider: row.label })}
                </a>
              )}
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
