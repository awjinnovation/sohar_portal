import type { CSSProperties, ReactNode } from 'react';
import type { Lang, Tenant } from '../api';
import type { Strings } from '../i18n';
import { Icon } from '../icons';

/** Brand band + sheet + footer: the same frame as the backend's layouts/public.blade.php. */
export function Layout(props: {
  tenant: Tenant | null;
  lang: Lang;
  t: Strings;
  onLang?: (lang: Lang) => void;
  children: ReactNode;
}) {
  const { tenant, lang, t, onLang, children } = props;
  const theme = tenant?.theme;
  const other: Lang = lang === 'ar' ? 'en' : 'ar';
  const canSwitch = !!onLang && !!tenant && tenant.locales.includes('ar') && tenant.locales.includes('en');

  return (
    <>
      <header
        className={`pb-band${theme?.header_image ? ' has-image' : ''}`}
        style={theme?.header_image ? ({ '--header-image': `url("${theme.header_image}")` } as CSSProperties) : undefined}
      >
        <div className="pb-wrap pb-top">
          {theme && (
            <span className={`pb-logo${theme.logo_chip ? ' chip' : ''}`}>
              {theme.logo ? <img src={theme.logo} alt="" decoding="async" /> : <Icon name="calendar2-heart" />}
            </span>
          )}
          <span className="pb-name">
            <b>{tenant?.name ?? ' '}</b>
            <span>{t.bookYourTicket}</span>
          </span>
          {canSwitch && (
            <button type="button" className="pb-icon-btn" lang={other} onClick={() => onLang!(other)}>
              <Icon name="translate" />
              <span>{t.otherLang}</span>
            </button>
          )}
        </div>
      </header>

      <main className="pb-sheet">
        <div className="pb-wrap">{children}</div>
      </main>

      {tenant && (
        <footer className="pb-foot">
          <div className="pb-wrap">
            {tenant.organization && (
              <div className="pb-org">
                {t.managedBy} {tenant.organization}
              </div>
            )}
            <nav className="pb-links">
              {tenant.privacy_url && (
                <a href={tenant.privacy_url} rel="noopener">
                  <Icon name="shield-check" /> {t.privacy}
                </a>
              )}
              {tenant.terms_url && (
                <a href={tenant.terms_url} rel="noopener">
                  <Icon name="file-earmark-text" /> {t.terms}
                </a>
              )}
              {tenant.support_email && (
                <a href={`mailto:${tenant.support_email}`}>
                  <Icon name="envelope" /> <span className="ltr">{tenant.support_email}</span>
                </a>
              )}
              {tenant.support_phone && (
                <a href={`tel:${tenant.support_phone.replace(/[^\d+]/g, '')}`}>
                  <Icon name="telephone" /> <span className="ltr">{tenant.support_phone}</span>
                </a>
              )}
            </nav>
            <div className="pb-platform">
              © {new Date().getFullYear()} {tenant.name}
            </div>
          </div>
        </footer>
      )}
    </>
  );
}

export type Tone = 'info' | 'warn' | 'error';

export function Note({ tone, icon, children }: { tone: Tone; icon: Parameters<typeof Icon>[0]['name']; children: ReactNode }) {
  return (
    <div className={`note ${tone}`} role={tone === 'error' ? 'alert' : 'status'}>
      <Icon name={icon} />
      <div>{children}</div>
    </div>
  );
}

export function Money({ minor, currency, decimals, format }: { minor: number; currency: string; decimals: number; format: (m: number, d: number) => string }) {
  return (
    <span className="money" dir="ltr">
      <span className="cur">{currency}</span>
      {format(minor, decimals)}
    </span>
  );
}
