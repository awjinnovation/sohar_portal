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

/** A page with no event to show: keep it out of search results. */
export function setNoIndex(): void {
  meta('robots').content = 'noindex';
}
