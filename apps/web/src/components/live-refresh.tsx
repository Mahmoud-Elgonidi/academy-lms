'use client';

import { useRouter } from 'next/navigation';
import { useEffect, useRef } from 'react';
import { RELAY, RESYNC, SIGNALS, SIGNAL_EVENT, concerns, needsFallback, refreshesPage, socketOptions, waitUntilNext, type Realtime, type Signal } from '@/lib/live';

/** Tells the page's own scripts a signal was heard (useSignal), whether or not the tab is in view. */
function announce(signal: string) {
  window.dispatchEvent(new CustomEvent(SIGNAL_EVENT, { detail: signal }));
}

/**
 * The page stays current by itself: one socket to Reverb (as the panels
 * have), on the person's own channel, signed through the portal
 * (/bff/realtime/auth, the token never reaches the page). A signal refreshes
 * the page from the API, keeping what is being typed; a page in a hidden tab
 * refreshes when it is looked at again. Without a socket, or with the relay
 * quiet, it refreshes every minute instead, as the panels do. Each signal is
 * also told to the page's scripts (a window event), so a screen keeping its
 * own state reads it again; the ones such a screen follows by itself (the
 * shared mushaf's) leave the page alone.
 */
export function LiveRefresh({ realtime }: { realtime: Realtime | null }) {
  const router = useRouter();
  const key = realtime?.enabled ? realtime.key : null;
  const channel = realtime?.channel;
  const host = realtime?.host ?? null;
  const port = realtime?.port ?? null;
  const tls = realtime?.tls ?? true;

  useEffect(() => {
    let stopped = false;
    let socket: { disconnect(): void } | null = null;
    let soon: ReturnType<typeof setTimeout> | undefined;
    let missed = false;
    const state = { connected: false, lastHeard: Date.now(), lastRefresh: Date.now() };

    const now = () => {
      state.lastRefresh = Date.now();
      router.refresh();
    };
    const refresh = () => {
      if (document.hidden) {
        missed = true;
        return;
      }
      clearTimeout(soon);
      // Signals come in bursts (a lesson, its people, its log): one refresh for them.
      soon = setTimeout(now, 250);
    };
    const heard = (then: () => void) => () => {
      state.lastHeard = Date.now();
      then();
    };
    const seen = () => {
      if (!document.hidden && missed) {
        missed = false;
        refresh();
      }
    };
    document.addEventListener('visibilitychange', seen);
    const fallback = setInterval(() => {
      if (document.hidden || !needsFallback(state, Date.now())) return;
      now();
      announce(RESYNC);
    }, 15_000);

    if (key && channel) {
      void import('pusher-js').then(({ default: Pusher }) => {
        if (stopped) return;
        const pusher = new Pusher(key, {
          ...socketOptions({ enabled: true, key, host, port, tls, channel }, window.location),
          channelAuthorization: {
            customHandler: ({ socketId, channelName }, done) => {
              fetch('/bff/realtime/auth', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ socket_id: socketId, channel_name: channelName }),
              })
                .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`realtime auth ${r.status}`))))
                .then((signed: { auth: string }) => done(null, signed))
                .catch((error: Error) => done(error, null));
            },
          },
        });
        socket = pusher;
        const own = pusher.subscribe(`private-${channel}`);
        for (const signal of SIGNALS) {
          own.bind(
            signal,
            heard(() => {
              announce(signal);
              if (refreshesPage(signal)) refresh();
            }),
          );
        }
        const relay = pusher.subscribe(RELAY.channel);
        relay.bind(RELAY.alive, heard(() => undefined));
        relay.bind(
          RELAY.resync,
          heard(() => {
            announce(RESYNC);
            refresh();
          }),
        );
        // Back after the network dropped: whatever changed meanwhile.
        let connectedOnce = false;
        pusher.connection.bind('state_change', ({ current }: { current: string }) => {
          state.connected = current === 'connected';
          if (!state.connected) return;
          state.lastHeard = Date.now();
          if (connectedOnce) {
            announce(RESYNC);
            refresh();
          }
          connectedOnce = true;
        });
      });
    }

    return () => {
      stopped = true;
      clearTimeout(soon);
      clearInterval(fallback);
      document.removeEventListener('visibilitychange', seen);
      socket?.disconnect();
    };
  }, [key, channel, host, port, tls, router]);

  return null;
}

/**
 * What the clock alone changes on a page (a lesson's room opening, starting,
 * closing): the page refreshes at the soonest of these moments, as the
 * panels' data-live-next does.
 */
export function RefreshAt({ moments }: { moments: string[] }) {
  const router = useRouter();
  const at = moments.join(',');
  useEffect(() => {
    const wait = waitUntilNext(at ? at.split(',') : [], Date.now());
    if (wait === null) return;
    const timer = setTimeout(() => router.refresh(), wait);
    return () => clearTimeout(timer);
  }, [at, router]);
  return null;
}

/**
 * A client component's own state follows these signals: `then` runs for each
 * one heard (and for a resync), with the signal's name. The socket is
 * LiveRefresh's; nothing here polls.
 */
export function useSignal(signals: readonly Signal[], then: (signal: string) => void) {
  const latest = useRef(then);
  useEffect(() => {
    latest.current = then;
  });
  const followed = signals.join(',');
  useEffect(() => {
    const names = followed.split(',');
    const heard = (event: Event) => {
      const signal = (event as CustomEvent<unknown>).detail;
      if (concerns(signal, names)) latest.current(signal as string);
    };
    window.addEventListener(SIGNAL_EVENT, heard);
    return () => window.removeEventListener(SIGNAL_EVENT, heard);
  }, [followed]);
}

/**
 * A page that shows what one of the signals a screen follows by itself
 * changes (the mistakes notebook: the words marked in the shared mushaf)
 * refreshes for it, once for a burst; a hidden tab when it is looked at again.
 */
export function RefreshOn({ signals }: { signals: Signal[] }) {
  const router = useRouter();
  const soon = useRef<ReturnType<typeof setTimeout>>(undefined);
  const missed = useRef(false);
  useSignal(signals, (signal) => {
    // A resync refreshes the page already (LiveRefresh).
    if (signal === RESYNC) return;
    if (document.hidden) {
      missed.current = true;
      return;
    }
    clearTimeout(soon.current);
    soon.current = setTimeout(() => router.refresh(), 400);
  });
  useEffect(() => {
    const seen = () => {
      if (document.hidden || !missed.current) return;
      missed.current = false;
      router.refresh();
    };
    document.addEventListener('visibilitychange', seen);
    return () => {
      document.removeEventListener('visibilitychange', seen);
      clearTimeout(soon.current);
    };
  }, [router]);
  return null;
}
