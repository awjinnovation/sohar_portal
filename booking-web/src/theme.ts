import type { Lang, Theme } from './api';
import { readJson, writeJson } from './storage';

/**
 * Tenant design tokens → CSS custom properties on <html>, the same values the
 * backend's partials/theme-tokens.blade.php prints. Set through the CSSOM
 * (style.setProperty), so the strict Content-Security-Policy needs no
 * 'unsafe-inline'. The last theme is kept in localStorage and applied before
 * the first request, so a returning visitor sees the brand at once.
 */
const CACHE_KEY = 'booking-theme';
const root = document.documentElement;
let current: Theme | null = null;
let applied: string[] = [];
const media = window.matchMedia?.('(prefers-color-scheme: dark)');

function isDark(theme: Theme): boolean {
  let stored: string | null = null;
  try {
    stored = localStorage.getItem('ui-theme');
  } catch {
    /* storage blocked */
  }
  const pref = stored === 'light' || stored === 'dark' ? stored : theme.mode;
  return pref === 'dark' || (pref === 'system' && !!media?.matches);
}

function paint(theme: Theme): void {
  const dark = isDark(theme);
  const vars = { ...theme.root, ...(dark ? theme.dark : theme.light) };
  for (const name of applied) if (!(name in vars)) root.style.removeProperty(name);
  for (const [name, value] of Object.entries(vars)) root.style.setProperty(name, value);
  applied = Object.keys(vars);
  root.dataset.theme = dark ? 'dark' : 'light';
  root.style.colorScheme = dark ? 'dark' : 'light';
}

function setHeadLink(rel: string, href: string | null, id: string): void {
  let link = document.getElementById(id) as HTMLLinkElement | null;
  if (!href) {
    link?.remove();
    return;
  }
  if (!link) {
    link = document.createElement('link');
    link.id = id;
    link.rel = rel;
    document.head.appendChild(link);
  }
  if (link.href !== href) link.href = href;
}

export function applyTheme(theme: Theme, cache = true): void {
  current = theme;
  paint(theme);
  setHeadLink('stylesheet', theme.fonts_url || null, 'tenant-fonts');
  setHeadLink('icon', theme.icon, 'tenant-icon');
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme.theme_color);
  if (cache) writeJson('local', CACHE_KEY, theme);
}

/** The theme of the last visit, if any. */
export function applyCachedTheme(): void {
  const theme = readJson<Theme>('local', CACHE_KEY);
  if (theme?.root && theme.light) applyTheme(theme, false);
}

media?.addEventListener?.('change', () => current && paint(current));

export function applyLang(lang: Lang): void {
  root.lang = lang;
  root.dir = lang === 'ar' ? 'rtl' : 'ltr';
}
