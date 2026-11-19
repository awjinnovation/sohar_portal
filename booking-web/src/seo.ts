import type { EventPayload } from './api';
import type { Strings } from './i18n';

/** Phone country code (tenant.country_code) → ISO country, for the event's address. */
const COUNTRY: Record<string, string> = { '968': 'OM', '971': 'AE', '966': 'SA', '974': 'QA', '965': 'KW', '973': 'BH' };

/** Trimmed, non-empty, without repeats (case ignored) and without the ones in `skip`. */
function unique(list: (string | null | undefined)[], skip: string[] = []): string[] {
  const seen = new Set(skip.map((x) => x.trim().toLowerCase()));
  const out: string[] = [];
  for (const raw of list) {
    const value = (raw ?? '').trim();
    const key = value.toLowerCase();
    if (value && !seen.has(key)) {
      seen.add(key);
      out.push(value);
    }
  }
  return out;
}

/** At most `max` characters, cut at a space. */
function clip(text: string, max: number): string {
  if (text.length <= max) return text;
  const cut = text.slice(0, max);
  const space = cut.lastIndexOf(' ');
  return (space > max / 2 ? cut.slice(0, space) : cut).trim();
}

/** The words shown under the event: its other names, then the organizer's search words. */
export function eventTags(event: EventPayload['event']): string[] {
  return unique([...(event.other_names ?? []), ...(event.search_terms ?? [])], [event.title]);
}

/**
 * What search engines get for an event page: the title (with the event's name
 * in the other language), a description with the dates and the event's own
 * description, keywords, and a schema.org Event (Google's event results).
 */
export function eventSeo(p: EventPayload, s: Strings, url: string) {
  const ev = p.event;
  const name = ev.title || s.event;
  const full = name === p.tenant.name ? name : `${name} · ${p.tenant.name}`;
  const others = unique(ev.other_names ?? [], [name]);
  const terms = ev.search_terms ?? [];

  const title = others.length ? `${s.seo_title(full)} | ${others[0]}` : s.seo_title(full);
  const description = clip([s.seo_description(name, ev.date_label), ev.description].filter(Boolean).join(' '), 300);
  const keywords = unique([name, s.seo_title(name), ...others, ...terms]);

  const prices = p.ticket_types.map((t) => t.price_minor);
  const decimals = p.tenant.currency_decimals;
  const offers = prices.length
    ? {
        '@type': 'Offer',
        url,
        price: (Math.min(...prices) / 10 ** decimals).toFixed(decimals),
        priceCurrency: p.tenant.currency,
        availability: p.ticket_types.some((t) => t.sales_open) ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
      }
    : undefined;

  // JSON.stringify leaves out the undefined fields.
  const data = {
    '@context': 'https://schema.org',
    '@type': 'Event',
    name,
    alternateName: others.length ? others : undefined,
    description: ev.description || description,
    keywords: terms.length ? terms.join(', ') : undefined,
    url,
    image: ev.image_url ? [ev.image_url] : undefined,
    startDate: ev.starts_at ?? undefined,
    endDate: ev.ends_at ?? undefined,
    eventStatus: 'https://schema.org/EventScheduled',
    eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
    inLanguage: p.lang,
    location: {
      '@type': 'Place',
      name: ev.venue || p.tenant.name,
      address: { '@type': 'PostalAddress', addressLocality: ev.venue || undefined, addressCountry: COUNTRY[p.tenant.country_code] },
    },
    organizer: { '@type': 'Organization', name: p.tenant.organization || p.tenant.name, url: location.origin },
    offers,
  };

  return { title, description, keywords, data };
}
