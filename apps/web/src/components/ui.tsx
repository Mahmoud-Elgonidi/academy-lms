import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';

const join = (...names: (string | false | null | undefined)[]) => names.filter(Boolean).join(' ');

type Tone = 'brand' | 'attention' | 'danger' | 'info' | 'muted';

const toneClass: Record<Tone, string> = {
  brand: 'bg-brand-soft text-brand-strong',
  attention: 'bg-attention-soft text-attention',
  danger: 'bg-danger-soft text-danger',
  info: 'bg-info-soft text-info',
  muted: 'bg-line/60 text-muted',
};

export function Badge({ tone = 'muted', children }: { tone?: Tone; children: ReactNode }) {
  return <span className={join('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', toneClass[tone])}>{children}</span>;
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'quiet' | 'danger'; wide?: boolean };

export function Button({ variant = 'primary', wide, className, ...rest }: ButtonProps) {
  return (
    <button
      {...rest}
      className={join(
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-control px-5 text-[15px] font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50',
        variant === 'primary' && 'bg-brand text-white hover:bg-brand-strong',
        variant === 'quiet' && 'border border-line bg-white text-ink hover:border-brand-line hover:bg-brand-soft',
        variant === 'danger' && 'bg-danger text-white hover:opacity-90',
        wide && 'w-full',
        className,
      )}
    />
  );
}

export function Card({ children, className, as: Tag = 'section' }: { children: ReactNode; className?: string; as?: 'section' | 'article' | 'div' | 'li' }) {
  return <Tag className={join('rounded-card border border-line bg-white p-5', className)}>{children}</Tag>;
}

export function Heading({ title, hint, action }: { title: string; hint?: ReactNode; action?: ReactNode }) {
  return (
    <header className="mb-5 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-2xl font-bold">{title}</h1>
        {hint ? <p className="mt-1 text-muted">{hint}</p> : null}
      </div>
      {action}
    </header>
  );
}

export function SectionTitle({ children, trailing, id }: { children: ReactNode; trailing?: ReactNode; id?: string }) {
  return (
    <div className="mb-3 mt-7 flex items-baseline justify-between gap-3">
      <h2 id={id} className="text-sm font-semibold text-muted">{children}</h2>
      {trailing ? <span className="text-sm text-muted">{trailing}</span> : null}
    </div>
  );
}

export function Empty({ children }: { children: ReactNode }) {
  return <p className="rounded-card border border-dashed border-line bg-white/60 p-8 text-center text-muted">{children}</p>;
}

export function Field({ label, hint, error, children }: { label: ReactNode; hint?: string; error?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-sm font-semibold">{label}</span>
      {children}
      {hint && !error ? <span className="mt-1 block text-xs text-muted">{hint}</span> : null}
      {error ? <span className="mt-1 block text-sm text-danger">{error}</span> : null}
    </label>
  );
}

const control =
  'w-full rounded-control border border-line bg-white px-3.5 py-2.5 text-[15px] outline-none transition-colors placeholder:text-muted/70 focus:border-brand focus:ring-2 focus:ring-brand-soft';

export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={join(control, 'min-h-11', props.className)} />;
}

export function Textarea(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea rows={3} {...props} className={join(control, props.className)} />;
}

export function Notice({ tone = 'info', children }: { tone?: Tone; children: ReactNode }) {
  return <div role="status" className={join('rounded-card px-4 py-3 text-sm', toneClass[tone])}>{children}</div>;
}

export { join };
export type { Tone };

const buttonLook = (variant: 'primary' | 'quiet' | 'danger' = 'primary') =>
  join(
    'inline-flex min-h-11 items-center justify-center gap-2 rounded-control px-5 text-[15px] font-semibold transition-colors',
    variant === 'primary' && 'bg-brand text-white hover:bg-brand-strong',
    variant === 'quiet' && 'border border-line bg-white text-ink hover:border-brand-line hover:bg-brand-soft',
    variant === 'danger' && 'bg-danger text-white hover:opacity-90',
  );

export { buttonLook };

/** A bar that shows how far along something is (0–100). */
export function Progress({ value, label }: { value: number; label?: string }) {
  const clamped = Math.max(0, Math.min(100, Math.round(value)));
  return (
    <div role="progressbar" aria-valuenow={clamped} aria-valuemin={0} aria-valuemax={100} aria-label={label} className="h-2 overflow-hidden rounded-full bg-line/70">
      <div className="bar-fill h-full rounded-full bg-brand" style={{ width: `${clamped}%` }} />
    </div>
  );
}

/** A number that matters on a page, with what it is. */
export function Stat({ value, label, tone = 'brand' }: { value: ReactNode; label: ReactNode; tone?: Tone }) {
  return (
    <div className="rounded-card border border-line bg-white p-4">
      <p className={join('font-heading text-2xl font-bold', tone === 'danger' ? 'text-danger' : tone === 'attention' ? 'text-attention' : 'text-ink')}>{value}</p>
      <p className="mt-0.5 text-sm text-muted">{label}</p>
    </div>
  );
}

export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...props} className={join(control, 'min-h-11 appearance-auto', props.className)} />;
}
