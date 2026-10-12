{{-- The academy's own privacy notice and terms (Privacy's public pages on this host), under every panel's sign-in, in the page's language. --}}
@php $lang = ['lang' => app()->getLocale()]; @endphp
<nav class="lms-legal" aria-label="{{ __('identity::auth.legal.label') }}">
    <a href="{{ route('privacy.notice', $lang, false) }}" target="_blank" rel="noopener">{{ __('identity::auth.legal.privacy') }}</a>
    <span aria-hidden="true">·</span>
    <a href="{{ route('privacy.terms', $lang, false) }}" target="_blank" rel="noopener">{{ __('identity::auth.legal.terms') }}</a>
</nav>
<style>
    .lms-legal { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; font-size: 0.8125rem; color: var(--gray-500); }
    .lms-legal a { color: var(--gray-600); text-decoration: underline; text-underline-offset: 3px; }
    .lms-legal a:hover, .lms-legal a:focus-visible { color: var(--primary-600); }
    .dark .lms-legal a { color: var(--gray-400); }
    .dark .lms-legal a:hover, .dark .lms-legal a:focus-visible { color: var(--primary-400); }
</style>
