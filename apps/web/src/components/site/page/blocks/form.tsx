import { countriesIn } from '@/lib/site/countries';
import { bookersOf, say, sayList, whatsappUrl, type FormBlock, type Tone } from '@/lib/site/page';
import { timeFormatOf } from '@/lib/time';
import { Icon, WhatsAppIcon } from '../../icons';
import { JoinAcademyForm } from '../../join-academy';
import { audienceRaw, type PageCtx } from '../context';
import { headingOf, TitleTag } from '../heading';
import { Section } from '../section';
import { PreviewGuard } from './preview-guard';

const NO_TRIALS = { booking: 'staff', response_hours: 24, notice_hours: 3, help_choose_subject: false, help_choose_subject_id: null } as const;

/**
 * The academy's doors (live): a free trial, and "teach with us" when the
 * academy shows it. The form and its flows are today's (JoinAcademyForm);
 * its anchors are always #join and #teach.
 */
export function Form({ block, ctx, tone }: { block: FormBlock; ctx: PageCtx; tone: Tone }) {
  const { site, t, locale } = ctx;
  const subjects = site.subjects.filter((s) => s.name);
  const who = bookersOf(block, ctx.audience);
  const lead = site.trials?.booking === 'family' ? 'join.leadBook' : 'join.lead';
  const words = headingOf(block, ctx, { title: 'join.title', lead, eyebrow: 'join.eyebrow' });
  // A page for adults says its own points; a families page whose form takes adults alone, the learner's (no child's level).
  const points = sayList(block.data.points, () => (ctx.audience !== 'adults' && who === 'adults' ? (t.raw('join.pointsSelf') as string[]) : audienceRaw<string[]>(ctx, 'join.points')));
  const whatsapp = block.data.whatsapp_button ? whatsappUrl(site.whatsapp ?? ctx.page.settings.whatsapp) : null;
  const talkLabel = say(block.data.whatsapp_label, () => t('cta.talk'));
  const form = (
    <JoinAcademyForm
      token={site.form_token}
      subjects={subjects.map((s) => ({ id: s.id, name: s.name }))}
      countries={countriesIn(locale, site.regions.flatMap((r) => r.countries))}
      utm={{ source: ctx.utm.utm_source, medium: ctx.utm.utm_medium, campaign: ctx.utm.utm_campaign }}
      teach={block.data.teach}
      chosenSubject={subjects.some((s) => s.id === ctx.chosenSubject) ? ctx.chosenSubject : undefined}
      trials={site.trials ?? NO_TRIALS}
      whatsapp={site.whatsapp}
      academy={site.academy.name}
      timeFormat={timeFormatOf(ctx.look.time_format)}
      who={who}
      adultsWords={ctx.audience === 'adults'}
    />
  );
  const guarded = ctx.preview ? <PreviewGuard note={t('preview.formNote')}>{form}</PreviewGuard> : form;
  const talk = whatsapp ? (
    <div className="ls-cta">
      <a className="ls-btn ls-btn-ghost" href={whatsapp} target="_blank" rel="noopener"><WhatsAppIcon />{talkLabel}</a>
    </div>
  ) : null;

  if (block.variant === 'split') {
    // Today's look, exactly: the night band, the aurora, the words beside the form.
    return (
      <section className="ls-section ls-join" data-tone={tone === 'paper' ? undefined : tone} id="join" data-block="form" data-space={block.style.space === 'normal' ? undefined : block.style.space}>
        <span id="teach" className="ls-anchor" aria-hidden="true" />
        {block.anchor !== 'join' ? <span id={block.anchor} className="ls-anchor" aria-hidden="true" /> : null}
        {tone === 'night' ? <div className="ls-aurora" aria-hidden="true"><span /><span /><span /></div> : null}
        <div className="ls-wrap ls-join-grid">
          <div data-reveal="start">
            {words.eyebrow ? <span className="ls-eyebrow">{words.eyebrow}</span> : null}
            {words.title ? <TitleTag block={block} ctx={ctx}>{words.title}</TitleTag> : null}
            {words.lead ? <p className="ls-lead" style={{ marginBlockStart: '1rem' }}>{words.lead}</p> : null}
            {points.length > 0 ? (
              <ul className="ls-join-points">
                {points.map((p) => <li key={p}><Icon name="star" />{p}</li>)}
              </ul>
            ) : null}
            {talk}
          </div>
          <div data-reveal="zoom">{guarded}</div>
        </div>
      </section>
    );
  }

  return (
    <Section block={block} tone={tone} className="sp-form" wrap={false}>
      <span id="join" className="ls-anchor" aria-hidden="true" />
      <span id="teach" className="ls-anchor" aria-hidden="true" />
      <div className="sp-wrap">
        <header className="sp-head" data-layout="stack" data-reveal="">
          <div>
            {words.eyebrow ? <p className="sp-eyebrow">{words.eyebrow}</p> : null}
            {words.title ? <TitleTag block={block} ctx={ctx} className="sp-title">{words.title}</TitleTag> : null}
          </div>
          {words.lead ? <p className="sp-lead">{words.lead}</p> : null}
        </header>
        {points.length > 0 ? (
          <ul className="sp-inline-points" data-reveal="">
            {points.map((p) => <li key={p}><Icon name="check" />{p}</li>)}
          </ul>
        ) : null}
        <div className="sp-form-card" data-reveal="">{guarded}</div>
        {whatsapp ? (
          <p className="sp-form-alt">
            <a className="sp-btn sp-btn-secondary" href={whatsapp} target="_blank" rel="noopener"><WhatsAppIcon /><span>{talkLabel}</span></a>
          </p>
        ) : null}
      </div>
    </Section>
  );
}

