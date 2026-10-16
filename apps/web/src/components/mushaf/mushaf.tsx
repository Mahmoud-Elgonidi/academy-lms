'use client';

import { useMaybeRoomContext } from '@livekit/components-react';
import { ConnectionState, RoomEvent, type Participant } from 'livekit-client';
import { useLocale, useTranslations } from 'next-intl';
import { useEffect, useId, useRef, useState, type FormEvent, type KeyboardEvent } from 'react';
import { markWord, turnMushaf, unmarkWord } from '@/app/[locale]/teach/actions';
import { Icon } from '@/components/icons';
import { useSignal } from '@/components/live-refresh';
import { quranFont } from '@/components/mushaf/font';
import { MushafText } from '@/components/mushaf/text';
import { Button, Notice, join } from '@/components/ui';
import { readMushaf } from '@/lib/actions/shared';
import { pageTitle } from '@/lib/hifz/hifz';
import {
  MUSHAF_TOPIC,
  actsOnRoomMessage,
  followsAfter,
  isAway,
  mushafNudge,
  pageInput,
  pageModel,
  pageToAsk,
  stepPage,
  swatchClass,
  teacherPageOf,
  withMark,
  wordMenu,
  type MushafWord,
} from '@/lib/mushaf/mushaf';
import { sequence } from '@/lib/mushaf/sync';
import type { MushafPage, MushafResult, WordRef } from '@/lib/mushaf/types';

type Menu = { word: MushafWord; top: number; left: number };

/**
 * A Quran lesson's shared mushaf (docs/architecture/02, section 22). Its
 * teacher turns the pages (prev, next, or a page by number) and, from the
 * room's opening until the report is handed in, taps a word to mark it wrong,
 * a hesitation or tajweed, or to take the mark off; the student and their
 * guardians follow the teacher's page and may look at another meanwhile,
 * with the way back offered. It stays in step by itself: in a LiveKit room
 * at once, the teacher's screen nudging the others over the room's data
 * channel after each change (they act only on a participant the server
 * signed as the teacher); and in every lesson through the mushaf signal on
 * each person's own channel. Each nudge reads the page again from the API.
 */
export function Mushaf({ initial, onChange, fill = false, readOnMount = false }: {
  initial: MushafPage;
  /** Each page drawn, so a screen that mounts the mushaf again starts from it. */
  onChange?: (page: MushafPage) => void;
  /** Fill the parent's height on wide screens, the text scrolling (beside the video). */
  fill?: boolean;
  /** Read the page again once mounted (the screen around it changed since it was read). */
  readOnMount?: boolean;
}) {
  const t = useTranslations('mushaf');
  const hifz = useTranslations('hifz');
  const common = useTranslations('common');
  const locale = useLocale();
  const titleId = useId();
  const room = useMaybeRoomContext();
  const [page, setPage] = useState(initial);
  const [following, setFollowing] = useState(true);
  // The page being opened, while its words are on their way.
  const [opening, setOpening] = useState<number | null>(null);
  const [menu, setMenu] = useState<Menu | null>(null);
  const [message, setMessage] = useState<string>();
  const [typed, setTyped] = useState('');
  const [seq] = useState(sequence);
  // What the reads ask while a change is on its way (state catches up on the next render).
  const followRef = useRef(true);
  const shownRef = useRef(initial.page);
  const openingRef = useRef(0);
  const wordButton = useRef<HTMLButtonElement | null>(null);
  const area = useRef<HTMLDivElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);

  const session = initial.session_id;
  const teacher = page.role === 'teacher';
  const marking = teacher && page.can_mark;

  const show = (answer: MushafPage, asked: number | undefined) => {
    const follows = followsAfter(answer, asked);
    // Another page: a word's menu has nothing left to mark.
    if (answer.page !== shownRef.current) setMenu(null);
    followRef.current = follows;
    shownRef.current = answer.page;
    setPage(answer);
    setFollowing(follows);
    onChange?.(answer);
  };

  /** Reads the page again: `asked`, else as following says. Opened by the person: a spinner, and the API's words if it fails. */
  const load = (asked: number | undefined, opened: boolean) => {
    if (seq.busy()) {
      seq.readLater();
      return;
    }
    const n = seq.read();
    if (opened) {
      openingRef.current = n;
      setOpening(asked ?? teacherPageOf(page));
    }
    void readMushaf(session, asked).then((done) => {
      if (opened && openingRef.current === n) setOpening(null);
      if (!seq.accept(n)) return;
      if (!done.ok) {
        if (opened) setMessage(done.message ?? common('problem'));
        return;
      }
      if (opened) setMessage(undefined);
      show(done.mushaf, asked);
    });
  };

  /** Reads again what is on screen: someone else changed it (a signal, the teacher's nudge in the room). */
  const reread = () => load(pageToAsk(page.role, followRef.current, shownRef.current), false);

  // The room's messages and the live signal read through the latest closure.
  const latest = useRef(reread);
  useEffect(() => {
    latest.current = reread;
  });

  useSignal(['mushaf.changed'], () => latest.current());

  useEffect(() => {
    if (!room) return;
    const heard = (_payload: Uint8Array, participant?: Participant, _kind?: unknown, topic?: string) => {
      if (actsOnRoomMessage(topic, participant?.metadata)) latest.current();
    };
    room.on(RoomEvent.DataReceived, heard);
    return () => {
      room.off(RoomEvent.DataReceived, heard);
    };
  }, [room]);

  useEffect(() => {
    if (readOnMount) latest.current();
  }, [readOnMount]);

  /** The teacher tells the room the mushaf changed (only the teacher's are acted on). */
  const nudge = () => {
    if (!room || room.state !== ConnectionState.Connected) return;
    room.localParticipant.publishData(mushafNudge(), { reliable: true, topic: MUSHAF_TOPIC }).catch(() => undefined);
  };

  /** A teacher's change: drawn at once where it can be, then the API's answer. */
  const write = (run: () => Promise<MushafResult>, opened?: number) => {
    const n = seq.write();
    if (opened !== undefined) {
      openingRef.current = n;
      setOpening(opened);
    }
    void run().then((done) => {
      const later = seq.written();
      if (opened !== undefined && openingRef.current === n) setOpening(null);
      if (done.ok) {
        setMessage(undefined);
        if (seq.accept(n)) show(done.mushaf, undefined);
        nudge();
      } else {
        setMessage(done.message ?? common('problem'));
      }
      // A read asked for meanwhile; or, after a refusal, what is drawn may not be so: the page as it stands, once nothing else is on its way.
      if (later || (!done.ok && !seq.busy())) latest.current();
    });
  };

  const go = (to: number) => {
    setMenu(null);
    setTyped('');
    if (teacher) {
      write(() => turnMushaf(session, to), to);
      return;
    }
    followRef.current = false;
    shownRef.current = to;
    load(to, true);
  };

  const backToTeacher = () => {
    followRef.current = true;
    load(undefined, true);
  };

  const submitPage = (e: FormEvent) => {
    e.preventDefault();
    const to = pageInput(typed, page.pages_total);
    if (to === null) {
      setMessage(t('badPage', { total: page.pages_total }));
      return;
    }
    go(to);
  };

  const openMenu = (word: MushafWord, button: HTMLButtonElement) => {
    if (menu?.word.key === word.key) {
      setMenu(null);
      return;
    }
    const box = area.current?.getBoundingClientRect();
    const at = button.getBoundingClientRect();
    if (!box) return;
    wordButton.current = button;
    const half = Math.min(96, box.width / 2);
    setMenu({ word, top: at.bottom - box.top + 6, left: Math.min(Math.max(at.left + at.width / 2 - box.left, half), box.width - half) });
  };

  const closeMenu = (refocus: boolean) => {
    setMenu(null);
    if (refocus) wordButton.current?.focus();
  };

  const mark = (word: WordRef, kind: string | null) => {
    closeMenu(true);
    setPage((p) => withMark(p, word, kind));
    write(() => (kind === null ? unmarkWord(session, word, page.page) : markWord(session, word, kind, page.page)));
  };

  // The menu takes the focus when it opens; a press outside it closes it.
  useEffect(() => {
    if (!menu) return;
    menuRef.current?.querySelector<HTMLButtonElement>('button')?.focus();
    const outside = (e: PointerEvent) => {
      const target = e.target as Node;
      if (menuRef.current?.contains(target) || wordButton.current?.contains(target)) return;
      setMenu(null);
    };
    document.addEventListener('pointerdown', outside);
    return () => document.removeEventListener('pointerdown', outside);
  }, [menu]);

  const menuKeys = (e: KeyboardEvent<HTMLDivElement>) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      closeMenu(true);
      return;
    }
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(e.key)) return;
    e.preventDefault();
    const items = [...(menuRef.current?.querySelectorAll<HTMLButtonElement>('[role^="menuitem"]') ?? [])];
    const at = items.indexOf(document.activeElement as HTMLButtonElement);
    const next = e.key === 'Home' ? 0 : e.key === 'End' ? items.length - 1 : (at + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
    items[next]?.focus();
  };

  const pieces = pageModel(page);
  const away = isAway(page, following);
  const prev = stepPage(page.page, -1, page.pages_total);
  const next = stepPage(page.page, 1, page.pages_total);
  const choices = menu ? wordMenu(page.kinds, menu.word.mark) : null;
  const legend = marking || page.marks.length > 0 || page.earlier.length > 0;
  const title = pageTitle(page, locale, hifz('page', { page: page.page }));

  return (
    <section aria-labelledby={titleId} className={join(quranFont.variable, 'rounded-card border border-line bg-white font-sans text-[15px] leading-relaxed text-ink [color-scheme:light]', fill && 'lg:flex lg:h-full lg:flex-col')}>
      <header className="space-y-3 border-b border-line p-4">
        <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
          <h2 id={titleId} className="flex items-center gap-2 text-lg font-bold">
            <Icon name="book" size={18} className="text-brand-strong" />
            {t('title')}
          </h2>
          <p className="text-sm text-muted" aria-live="polite">
            <span className="font-semibold text-ink">
              {title}
            </span>
            {' · '}
            {hifz('juz', { n: page.juz })}
          </p>
        </div>
        <p className="text-sm text-muted">{teacher ? (page.can_mark ? t('teacherLead') : t('teacherClosed')) : t('followLead')}</p>
        <div className="flex flex-wrap items-center gap-2">
          <Button type="button" variant="quiet" className="min-h-10! px-3! text-sm!" onClick={() => prev && go(prev)} disabled={prev === null}>
            <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
            {t('prev')}
          </Button>
          <form onSubmit={submitPage} className="flex items-center gap-1.5">
            <input
              value={typed}
              onChange={(e) => setTyped(e.target.value)}
              inputMode="numeric"
              enterKeyHint="go"
              placeholder={String(page.page)}
              aria-label={t('pageField')}
              title={t('pageOf', { page: page.page, total: page.pages_total })}
              className="min-h-10 w-20 rounded-control border border-line bg-white px-2 text-center text-[15px] tabular-nums outline-none transition-colors placeholder:text-muted focus:border-brand focus:ring-2 focus:ring-brand-soft"
            />
            <Button type="submit" variant="quiet" className="min-h-10! px-3! text-sm!">
              {t('go')}
            </Button>
          </form>
          <Button type="button" variant="quiet" className="min-h-10! px-3! text-sm!" onClick={() => next && go(next)} disabled={next === null}>
            {t('next')}
            <Icon name="chevron" size={16} className="rtl:-scale-x-100" />
          </Button>
          {opening !== null ? (
            <span className="text-sm text-muted" role="status">
              {common('loading')}
            </span>
          ) : null}
        </div>
        {away ? (
          <div className="flex flex-wrap items-center justify-between gap-2 rounded-control bg-info-soft px-3 py-2 text-sm text-info">
            <span>{t('teacherOn', { page: teacherPageOf(page) })}</span>
            <button type="button" onClick={backToTeacher} className="font-semibold underline-offset-4 hover:underline">
              {t('backToTeacher')}
            </button>
          </div>
        ) : null}
        {message ? <Notice tone="danger">{message}</Notice> : null}
      </header>

      <div className={join('p-4 sm:p-5', fill && 'lg:min-h-0 lg:flex-1 lg:overflow-y-auto')}>
        <div ref={area} className={join('relative transition-opacity', opening !== null && 'opacity-60')} aria-busy={opening !== null}>
          {/* While another page is on its way, its words are not this one's to mark. */}
          <MushafText pieces={pieces} kinds={page.kinds} onWord={marking && opening === null ? openMenu : undefined} openWord={menu?.word.key ?? null} />
          {menu && choices ? (
            <div
              ref={menuRef}
              role="menu"
              aria-label={t('wordMenu', { word: menu.word.text })}
              onKeyDown={menuKeys}
              className="absolute z-20 w-48 -translate-x-1/2 rounded-control border border-line bg-white p-1.5 text-sm shadow-lg"
              style={{ top: menu.top, left: menu.left }}
            >
              <p lang="ar" aria-hidden="true" className="truncate px-2.5 pb-1 pt-0.5 text-center font-quran text-xl text-ink">
                {menu.word.text}
              </p>
              {choices.kinds.map((c) => (
                <button
                  key={c.kind}
                  type="button"
                  role="menuitemradio"
                  aria-checked={c.chosen}
                  onClick={() => mark(menu.word, c.kind)}
                  className="flex min-h-10 w-full items-center gap-2 rounded-control px-2.5 text-start font-semibold hover:bg-paper focus-visible:bg-paper"
                >
                  <span aria-hidden="true" className={join('size-3 shrink-0 rounded-full', swatchClass(c.kind))} />
                  <span className="flex-1">{c.label}</span>
                  {c.chosen ? <Icon name="check" size={16} className="text-brand-strong" /> : null}
                </button>
              ))}
              {choices.clear ? (
                <button
                  type="button"
                  role="menuitem"
                  onClick={() => mark(menu.word, null)}
                  className="mt-1 flex min-h-10 w-full items-center gap-2 rounded-control border-t border-line px-2.5 text-start text-muted hover:bg-paper focus-visible:bg-paper"
                >
                  <Icon name="x" size={16} />
                  {t('clear')}
                </button>
              ) : null}
            </div>
          ) : null}
        </div>
        {legend ? (
          <ul className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-muted">
            <li className="font-semibold">{t('legendNow')}</li>
            {Object.entries(page.kinds).map(([kind, label]) => (
              <li key={kind} className="inline-flex items-center gap-1.5">
                <span aria-hidden="true" className={join('inline-block size-3 rounded-full', swatchClass(kind))} />
                {label}
              </li>
            ))}
            <li className="inline-flex items-center gap-1.5">
              <span aria-hidden="true" className="inline-block w-5 border-b-2 border-dotted border-muted/60" />
              {t('legendEarlier')}
            </li>
          </ul>
        ) : null}
        <p className="mt-3 text-xs text-muted">{page.attribution}</p>
      </div>
    </section>
  );
}
