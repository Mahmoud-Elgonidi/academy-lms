'use client';

import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { RELAY, SIGNALS, needsFallback, socketOptions, waitUntilNext, type Realtime } from '@/lib/live';

/**
 * The page stays current by itself: one socket to Reverb (as the panels
 * have), on the person's own channel, signed through the portal
 * (/bff/realtime/auth, the token never reaches the page). A signal refreshes
 * the page from the API, keeping what is being typed; a page in a hidden tab
 * refreshes when it is looked at again. Without a socket, or with the relay
 * quiet, it refreshes every minute instead, as the panels do.
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
      if (!document.hidden && needsFallback(state, Date.now())) now();
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
        for (const signal of SIGNALS) own.bind(signal, heard(refresh));
        const relay = pusher.subscribe(RELAY.channel);
        relay.bind(RELAY.alive, heard(() => undefined));
        relay.bind(RELAY.resync, heard(refresh));
        // Back after the network dropped: whatever changed meanwhile.
        let connectedOnce = false;
        pusher.connection.bind('state_change', ({ current }: { current: string }) => {
          state.connected = current === 'connected';
          if (!state.connected) return;
          state.lastHeard = Date.now();
          if (connectedOnce) refresh();
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
