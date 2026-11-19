/**
 * Search-engine head tags. Google renders the app, so the title, description and
 * canonical address set here are what it indexes for /e/{id}. Only event pages
 * may be indexed: nginx sends `X-Robots-Tag: noindex` for every other address,
 * and pages that show no event (not found, load error, "/") add the meta tag.
 */
function meta(name: string): HTMLMetaElement {
  let el = document.head.querySelector<HTMLMetaElement>(`meta[name="${name}"]`);
  if (!el) {
    el = document.createElement('meta');
    el.name = name;
    document.head.appendChild(el);
  }
  return el;
}

/** The event page: its title and description, and the address without query (payment returns, ?lang). */
export function setEventHead(title: string, description: string): void {
  document.title = title;
  meta('description').content = description;
  let link = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');
  if (!link) {
    link = document.createElement('link');
    link.rel = 'canonical';
    document.head.appendChild(link);
  }
  link.href = location.origin + location.pathname.replace(/\/$/, '');
  document.head.querySelector('meta[name="robots"]')?.remove();
}

/** The events index at "/": its title and description, canonical to the site root. */
export function setIndexHead(title: string, description: string): void {
  document.title = title;
  meta('description').content = description;
  let link = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');
  if (!link) {
    link = document.createElement('link');
    link.rel = 'canonical';
    document.head.appendChild(link);
  }
  link.href = location.origin + '/';
  document.head.querySelector('meta[name="robots"]')?.remove();
}

/** A page with no event to show: keep it out of search results. */
export function setNoIndex(): void {
  meta('robots').content = 'noindex';
}

/** <meta name="keywords">: Google ignores it, other engines may read it. Empty removes it. */
export function setKeywords(words: string[]): void {
  if (words.length === 0) {
    document.head.querySelector('meta[name="keywords"]')?.remove();
    return;
  }
  meta('keywords').content = words.join(', ');
}

/**
 * schema.org data for search engines (Google's event results), one
 * <script type="application/ld+json"> in the head; null removes it. A data
 * block, never run, so the CSP's script-src does not apply to it.
 */
export function setStructuredData(data: object | null): void {
  let el = document.getElementById('event-jsonld');
  if (!data) {
    el?.remove();
    return;
  }
  if (!el) {
    el = document.createElement('script');
    el.id = 'event-jsonld';
    el.setAttribute('type', 'application/ld+json');
    document.head.appendChild(el);
  }
  el.textContent = JSON.stringify(data);
}
