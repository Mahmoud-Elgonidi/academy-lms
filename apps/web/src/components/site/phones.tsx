import type { CSSProperties, ReactNode } from 'react';
import { CreatureArt, IslandArt } from '@/components/games/art';
import { islandBox, type Look } from '@/lib/games/art';
import '@/components/games/games.css';
import '@/components/games/islands.css';

/*
 * The app as the public pages show it: phones drawn in the page (no
 * pictures to keep up to date), each screen a few parts of the real one
 * that move. Decorative: aria-hidden, the words around them say what they
 * show. The journey's art keeps its own palette (game art, not the brand).
 */

const BUDDY: Look = { kind: 'bird', body: '#74C0FC', wing: '#4DABF7', belly: '#E7F5FF', tuft: '#FF6B6B', beak: '#FF922B', pitch: 1 };

/** The words a phone's screens show, from the page's messages. */
export type PhoneWords = {
  guardian: { hello: string; child: string; next: string; subject: string; when: string; soon: string; report: string; reportLine: string; plan: string; balance: string; lessons: string };
  student: { stars: string; streak: string; mission: string };
  teacher: { today: string; live: string; lessons: [string, string, string]; times: [string, string, string]; voice: string; drafted: string };
  /** A page for adults: the learner's own screens (no guardian, no child, no game), and the teacher's day with adult learners. */
  adults: {
    learner: { hello: string; title: string; next: string; course: string; when: string; soon: string; progress: string; courses: { name: string; value: string }[]; task: string; taskLine: string; certificates: string; certificatesValue: string };
    session: { title: string; course: string; feedback: string; feedbackLine: string; units: string; unitsValue: string; unitsDone: string; task: string; taskLine: string; certificate: string; certificateLine: string };
    lessons: [string, string, string];
  };
};

/** The screens a page shows for its audience: an adult learns for themselves; a child has a guardian and a journey. */
export type PhoneSet = 'families' | 'adults';

export function Phone({ position, children, style }: { position?: 'center' | 'start' | 'end'; children: ReactNode; style?: CSSProperties }) {
  return (
    <div className="ls-phone" data-pos={position} style={style} aria-hidden="true">
      <div className="ls-screen">{children}</div>
    </div>
  );
}

/** The phones' screens for the page's audience, in the order they stand (start, centre, end). */
export function screensFor(set: PhoneSet, w: PhoneWords): { start: ReactNode; center: ReactNode; end: ReactNode } {
  if (set === 'adults') {
    return {
      start: <SessionScreen w={w.adults.session} />,
      center: <LearnerScreen w={w.adults.learner} />,
      end: <TeacherScreen w={{ ...w.teacher, lessons: w.adults.lessons }} />,
    };
  }
  return {
    start: <StudentScreen w={w.student} />,
    center: <GuardianScreen w={w.guardian} />,
    end: <TeacherScreen w={w.teacher} />,
  };
}

/** A parent's home: the next lesson, the last report, the month's plan. */
export function GuardianScreen({ w }: { w: PhoneWords['guardian'] }) {
  return (
    <>
      <div className="ls-s-top">
        <small>{w.hello}</small>
        <strong>{w.child}</strong>
      </div>
      <div className="ls-s-body">
        <div className="ls-s-card">
          <div className="ls-s-row">
            <h4>{w.next}</h4>
            <span className="ls-pill" data-tone="gold">{w.soon}</span>
          </div>
          <p>{w.subject}</p>
          <small>{w.when}</small>
        </div>
        <div className="ls-s-card">
          <h4>{w.report}</h4>
          <div className="ls-stars-row">★★★★★</div>
          <p>{w.reportLine}</p>
        </div>
        <div className="ls-s-card">
          <div className="ls-s-row">
            <h4>{w.plan}</h4>
            <span className="ls-pill">72%</span>
          </div>
          <div className="ls-meter"><i /></div>
        </div>
        <div className="ls-s-card ls-s-row">
          <h4>{w.balance}</h4>
          <p>{w.lessons}</p>
        </div>
      </div>
    </>
  );
}

/** A child's journey: their island, their buddy, the stations and the stars. */
export function StudentScreen({ w }: { w: PhoneWords['student'] }) {
  const box = islandBox('birds');
  return (
    <div className="ls-s-sky">
      <div className="ls-s-hud">
        <span>⭐ {w.stars}</span>
        <span>🔥 {w.streak}</span>
      </div>
      <div className="ls-isle">
        <div className="ls-isle-art" style={{ width: box.width, height: box.height, transform: 'scale(0.95)' }}>
          <IslandArt islandKey="birds" locked={false} />
        </div>
      </div>
      <div className="ls-path">
        <i data-done="" />
        <i data-done="" />
        <i data-done="" />
        <i data-now="" />
        <i />
      </div>
      <div className="ls-buddy">
        <CreatureArt look={BUDDY} size={64} motion />
      </div>
      <div className="ls-s-card" style={{ position: 'absolute', insetInline: '0.6rem', insetBlockEnd: '0.6rem', zIndex: 3 }}>
        <p>{w.mission}</p>
      </div>
    </div>
  );
}

/** An adult learner's home: their next live session, their courses with progress, their certificates. */
export function LearnerScreen({ w }: { w: PhoneWords['adults']['learner'] }) {
  return (
    <>
      <div className="ls-s-top">
        <small>{w.hello}</small>
        <strong>{w.title}</strong>
      </div>
      <div className="ls-s-body">
        <div className="ls-s-card">
          <div className="ls-s-row">
            <h4>{w.next}</h4>
            <span className="ls-pill" data-tone="gold">{w.soon}</span>
          </div>
          <p>{w.course}</p>
          <small>{w.when}</small>
        </div>
        <div className="ls-s-card">
          <h4>{w.progress}</h4>
          {w.courses.map((course) => (
            <div className="ls-s-course" key={course.name}>
              <div className="ls-s-row">
                <span>{course.name}</span>
                <b>{course.value}</b>
              </div>
              <div className="ls-meter"><i style={{ inlineSize: course.value }} /></div>
            </div>
          ))}
        </div>
        <div className="ls-s-card">
          <h4>{w.task}</h4>
          <p>{w.taskLine}</p>
        </div>
        <div className="ls-s-card ls-s-row">
          <h4>{w.certificates}</h4>
          <p>{w.certificatesValue}</p>
        </div>
      </div>
    </>
  );
}

/** After a session: the teacher's notes, the units done, the next task and the certificate it leads to. */
export function SessionScreen({ w }: { w: PhoneWords['adults']['session'] }) {
  return (
    <>
      <div className="ls-s-top">
        <small>{w.course}</small>
        <strong>{w.title}</strong>
      </div>
      <div className="ls-s-body">
        <div className="ls-s-card">
          <h4>{w.feedback}</h4>
          <div className="ls-stars-row">★★★★★</div>
          <p className="ls-s-quote">{w.feedbackLine}</p>
        </div>
        <div className="ls-s-card">
          <div className="ls-s-row">
            <h4>{w.units}</h4>
            <span className="ls-pill">{w.unitsValue}</span>
          </div>
          <div className="ls-meter"><i style={{ inlineSize: w.unitsDone }} /></div>
        </div>
        <div className="ls-s-card">
          <h4>{w.task}</h4>
          <p>{w.taskLine}</p>
        </div>
        <div className="ls-s-card ls-s-cert">
          <span className="ls-s-seal" aria-hidden="true">✓</span>
          <div>
            <p>{w.certificate}</p>
            <small>{w.certificateLine}</small>
          </div>
        </div>
      </div>
    </>
  );
}

/** A teacher's day: lessons, the one live now, a report drafted from their voice. */
export function TeacherScreen({ w }: { w: PhoneWords['teacher'] }) {
  return (
    <>
      <div className="ls-s-top">
        <small>{w.today}</small>
        <strong>3</strong>
      </div>
      <div className="ls-s-body">
        {w.lessons.map((lesson, i) => (
          <div className="ls-s-card ls-s-row" key={lesson}>
            <div>
              <p>{lesson}</p>
              <small>{w.times[i]}</small>
            </div>
            {i === 0 ? (
              <span className="ls-pill" data-tone="live">
                <i className="ls-live-dot" />
                {w.live}
              </span>
            ) : null}
          </div>
        ))}
        <div className="ls-s-card">
          <h4>{w.voice}</h4>
          <div className="ls-wave">
            {Array.from({ length: 22 }, (_, i) => (
              <i key={i} style={{ '--b': i } as CSSProperties} />
            ))}
          </div>
          <small>{w.drafted}</small>
        </div>
      </div>
    </>
  );
}
