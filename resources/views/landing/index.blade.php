@php
    $title = 'Mawsim Event Platform';
    $description = 'A white-label event app for Gulf organizers: tickets, a cashless wallet, deposits and loyalty points, in your own brand.';
    $home = rtrim(url('/'), '/').'/';
    $ogImage = asset('landing/img/web_demo_branding.jpg');
    $v = fn (string $f) => @filemtime(public_path('landing/'.$f)) ?: 1;
    $done = is_array($demoDone ?? null) ? $demoDone : null;
    $formError = $errors->first();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0C1820">
<link rel="canonical" href="{{ $home }}">
<link rel="alternate" hreflang="en" href="{{ $home }}?lang=en">
<link rel="alternate" hreflang="ar" href="{{ $home }}?lang=ar">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Mawsim">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $home }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="1400">
<meta property="og:image:height" content="875">
<meta property="og:image:alt" content="Mawsim branding editor with a live phone preview">
<meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_OM' : 'en_US' }}">
<meta property="og:locale:alternate" content="{{ $locale === 'ar' ? 'en_US' : 'ar_OM' }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $ogImage }}">
<link rel="icon" href="{{ asset('landing/favicon.svg') }}?v={{ $v('favicon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@@12..96,500;12..96,700;12..96,800&family=Readex+Pro:wght@300;400;500;600;700&family=Reem+Kufi:wght@500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="{{ asset('landing/landing.css') }}?v={{ $v('landing.css') }}">
</head>
<body>
<header class="top">
  <div class="wrap">
    <a class="brand" href="#top" aria-label="Mawsim"><b>Mawsim</b><span>موسم</span></a>
    <nav class="nav" aria-label="Sections">
      <a href="#journey" data-i18n="nav_how">How it works</a>
      <a href="#screens" data-i18n="nav_screens">Screens</a>
      <a href="#brand" data-i18n="nav_brand">Your brand</a>
      <a href="#plans" data-i18n="nav_plans">Plans</a>
      <a href="#faq" data-i18n="nav_faq">FAQ</a>
    </nav>
    <div class="top-actions">
      <button class="chip-btn" id="langBtn" type="button" aria-label="Switch language">عربي</button>
      <a class="btn btn-accent btn-sm" href="#demo" data-i18n="cta_demo">Book a demo</a>
    </div>
  </div>
</header>

<main id="top">
<section class="hero" aria-labelledby="heroTitle" style="padding-block:0">
  <svg class="lights" id="lights" viewBox="0 0 1200 180" preserveAspectRatio="xMidYMin slice" aria-hidden="true"></svg>
  <div class="wrap">
    <div>
      <p class="eyebrow" data-i18n="hero_eyebrow">White-label event app for the Gulf</p>
      <h1 id="heroTitle" style="margin-top:18px"><span data-i18n="hero_h1a">Your season.</span><br><em data-i18n="hero_h1b">Your app.</em></h1>
      <p class="lede" data-i18n="hero_lede">Mawsim gives festivals, governorates, malls and expos their own branded app: tickets, a cashless wallet, cup and wristband deposits, and loyalty points. One platform, configured for you, not built from scratch.</p>
      <div class="hero-cta">
        <a class="btn btn-accent" href="#demo" data-i18n="cta_demo">Book a demo</a>
        <a class="btn btn-ghost" href="#journey" data-i18n="cta_how">See how the wallet works</a>
      </div>
      <ul class="facts">
        <li data-i18n="fact_1">Arabic-first, with English</li>
        <li data-i18n="fact_2">Amounts in OMR to the baisa</li>
        <li data-i18n="fact_3">Bank gateway built in</li>
        <li data-i18n="fact_4">Many organizers, one platform</li>
      </ul>
    </div>

    <div class="stage">
      <div class="phone" id="phone" aria-label="Live preview of the app" role="img">
        <div class="screen" id="screen">
          <div class="island"></div>
          <div class="band"></div>
          <div class="sbar"><span>9:41</span><span>●●● ▮</span></div>
          <div class="p-head">
            <div class="p-logo" id="pLogo">H</div>
            <div class="p-hello"><b data-i18n="p_hello">Good evening, Salim</b><small id="pOrg">Harbour Lights Festival</small></div>
            <div class="p-bell"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg></div>
          </div>
          <div class="p-wallet">
            <div class="p-row"><span style="font-size:11px;opacity:.9" data-i18n="p_wallet">Wallet</span><span class="p-qr" data-i18n="p_qr">QR Pay</span></div>
            <div class="p-bal-l" data-i18n="p_balance">Wallet balance</div>
            <div class="p-bal"><small>OMR</small><span id="pBal">12.450</span></div>
            <div class="p-chips"><span class="p-chip"><i></i><span data-i18n="p_points">340 pts ≈ OMR 3.400</span></span><span class="p-chip" data-i18n="p_held">1 cup deposit · OMR 0.500</span></div>
            <div class="p-btns"><button class="p-btn solid" id="pPay" type="button" data-i18n="p_pay">Pay</button><button class="p-btn glass" type="button" id="pTop" data-i18n="p_top">Top up</button></div>
          </div>
          <div class="p-qa">
            <div><span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M16 15h2"/></svg></span><b style="font-weight:500" data-i18n="p_q1">Top up</b></div>
            <div><span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><path d="M14 14h2v2h-2zM18 18h2v2h-2z"/></svg></span><b style="font-weight:500" data-i18n="p_q2">Pay</b></div>
            <div><span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M4 8a2 2 0 0 0 2-2h12a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H6a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/></svg></span><b style="font-weight:500" data-i18n="p_q3">Tickets</b></div>
            <div><span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 3l2.6 5.5 6 .7-4.4 4.1 1.2 5.9L12 16.3 6.6 19.2l1.2-5.9L3.4 9.2l6-.7z"/></svg></span><b style="font-weight:500" data-i18n="p_q4">Points</b></div>
            <div><span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M4 6l5-2 6 2 5-2v14l-5 2-6-2-5 2z"/><path d="M9 4v14M15 6v14"/></svg></span><b style="font-weight:500" data-i18n="p_q5">Map</b></div>
          </div>
          <div class="p-sheet">
            <div class="p-ann"><span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 20l4-12 8 8z"/><path d="M14 4l1 3M19 6l-2 2M20 11l-3 0"/></svg></span><span style="background:none;width:auto;height:auto;display:block" data-i18n="p_ann">Gates open at 4:00 pm. Fireworks at 9:30.</span></div>
            <div class="p-sec"><span data-i18n="p_featured">Featured</span><a href="#screens" data-i18n="p_all">See all</a></div>
            <div class="p-event">
              <span class="p-tag" data-i18n="p_tag">Opening night</span>
              <div class="p-event-in"><div class="p-date">22<small data-i18n="p_mon">DEC</small></div><div class="p-ev-t"><b id="pEv">Opening Night on the Corniche</b><small class="money">OMR 0.500</small></div></div>
            </div>
          </div>
          <div class="p-nav">
            <div class="on"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3l9 8h-3v9h-5v-6h-2v6H6v-9H3z"/></svg><span data-i18n="p_n1">Home</span></div>
            <div><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/></svg><span data-i18n="p_n2">Explore</span></div>
            <div class="mid"><span><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="3" y="6" width="18" height="13" rx="3"/><path d="M16 12h2M3 9h15"/></svg></span></div>
            <div><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 8a2 2 0 0 0 2-2h12a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H6a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/></svg><span data-i18n="p_n3">Tickets</span></div>
            <div><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span data-i18n="p_n4">More</span></div>
          </div>
          <div class="p-pay" id="pPayView" hidden>
            <button class="p-close" id="pClose" type="button" aria-label="Close">✕</button>
            <b style="font-size:15px" data-i18n="p_pay_t">Show this code to the vendor</b>
            <canvas id="qr" width="29" height="29" aria-label="Sample pay code"></canvas>
            <span class="ring" id="pCount">0:60</span>
            <div class="avail"><span data-i18n="p_avail">Available to spend</span><b class="money">OMR 11.950</b></div>
            <span class="demo" data-i18n="p_demo">Sample code for this preview. Real codes refresh every 60 seconds and work once.</span>
          </div>
        </div>
      </div>
      <div class="try">
        <span class="try-label" data-i18n="try_label">Try a brand</span>
        <div class="swatches" id="swatches" role="group" aria-label="Example organizers"></div>
        <p class="try-note" data-i18n="try_note">Same app, re-branded from settings. Tap Pay to see the vendor code.</p>
      </div>
    </div>
  </div>
</section>

<section id="journey" aria-labelledby="jTitle">
  <div class="wrap">
    <div class="sec-head">
      <p class="eyebrow" data-i18n="j_eyebrow">The visitor's evening</p>
      <h2 id="jTitle" data-i18n="j_title">One wallet for the whole visit</h2>
      <p data-i18n="j_lede">Visitors top up once. After that, every ticket, karak and cup deposit is a scan, and every rial spent earns points.</p>
    </div>
    <ol class="journey" style="margin:0;padding:0;list-style:none">
      <li class="step"><span class="n">01</span><h3 data-i18n="s1_t">Top up</h3><p data-i18n="s1_p">Card payment through your bank's gateway. Optional top-up bonuses arrive as separate promo credit.</p><div class="slip"><span>Top up</span><b class="plus">+10.000</b></div></li>
      <li class="step"><span class="n">02</span><h3 data-i18n="s2_t">Tickets</h3><p data-i18n="s2_p">Priced by the server, paid from the wallet in one tap. The QR lands in the Tickets tab.</p><div class="slip"><span>2 × entry</span><b>−1.000</b></div></li>
      <li class="step"><span class="n">03</span><h3 data-i18n="s3_t">Stalls</h3><p data-i18n="s3_p">The visitor shows a pay code. The vendor keys in the amount and scans. No cash, no change.</p><div class="slip"><span>Karak ×2</span><b>−0.300</b></div></li>
      <li class="step"><span class="n">04</span><h3 data-i18n="s4_t">Deposits</h3><p data-i18n="s4_p">Cups, wristbands and lockers are held from the balance and released when the item comes back.</p><div class="slip"><span>Cup held</span><b>0.500</b></div></li>
      <li class="step"><span class="n">05</span><h3 data-i18n="s5_t">Points</h3><p data-i18n="s5_p">Spending earns points with tier multipliers. Points convert into wallet credit.</p><div class="slip"><span>Earned</span><b class="plus">+13 pts</b></div></li>
      <li class="step"><span class="n">06</span><h3 data-i18n="s6_t">Refund</h3><p data-i18n="s6_p">Unused cash goes back on request within your refund window. Promo credit stays promotional.</p><div class="slip"><span>Refundable</span><b>8.200</b></div></li>
    </ol>
  </div>
</section>

<section id="screens" class="band-alt" aria-labelledby="scTitle">
  <div class="wrap">
    <div class="sec-head">
      <p class="eyebrow" data-i18n="sc_eyebrow">Real screens</p>
      <h2 id="scTitle" data-i18n="sc_title">Built and tested, not a concept</h2>
      <p data-i18n="sc_lede">These are screenshots of the working app, branded for a sample organizer. Nothing in this row is a mockup.</p>
    </div>
    <div class="rail">
      <div class="shot"><figure><img src="{{ asset('landing/img/ui_demo_home.jpg') }}" alt="App home with wallet card, quick actions and featured events" loading="lazy" width="560" height="1212"></figure><h3 data-i18n="sh1_t">Home</h3><p data-i18n="sh1_p">Wallet up top, then announcements and what's on tonight.</p></div>
      <div class="shot"><figure><img src="{{ asset('landing/img/wallet_demo_payqr.jpg') }}" alt="Pay code screen with QR and countdown" loading="lazy" width="560" height="1212"></figure><h3 data-i18n="sh2_t">Pay code</h3><p data-i18n="sh2_p">Signed, single use and refreshed every minute.</p></div>
      <div class="shot"><figure><img src="{{ asset('landing/img/wallet_demo_points.jpg') }}" alt="Points screen with tier progress" loading="lazy" width="560" height="1212"></figure><h3 data-i18n="sh3_t">Points and tiers</h3><p data-i18n="sh3_p">Bronze, Silver, Gold, with expiry warnings and history.</p></div>
      <div class="shot"><figure><img src="{{ asset('landing/img/ui_demo_ticket_viewer.jpg') }}" alt="Ticket with large QR code" loading="lazy" width="560" height="1212"></figure><h3 data-i18n="sh4_t">Tickets</h3><p data-i18n="sh4_p">A large QR, ready for quick scanning at the gate.</p></div>
    </div>
  </div>
</section>

<section aria-labelledby="vTitle">
  <div class="wrap split rev">
    <div class="split-text">
      <p class="eyebrow" data-i18n="v_eyebrow">For vendors</p>
      <h2 id="vTitle" data-i18n="v_title">A till in every stall owner's phone</h2>
      <p data-i18n="v_lede">Stall staff sign in with their number and get a vendor mode: a keypad, a scanner and today's totals. You onboard vendors from the dashboard.</p>
      <ul class="ticks">
        <li><span data-i18n="v_1"><b>Charge in two taps.</b> Key in the amount, scan the visitor's code.</span></li>
        <li><span data-i18n="v_2"><b>Hold and return deposits.</b> Cups and wristbands are released back to the wallet on drop-off.</span></li>
        <li><span data-i18n="v_3"><b>Settle with confidence.</b> Every sale is on the ledger with sales, refunds and net per day.</span></li>
      </ul>
    </div>
    <div class="duo">
      <figure><img src="{{ asset('landing/img/wallet_demo_merchant_keypad.jpg') }}" alt="Vendor keypad for charging a visitor" loading="lazy" width="560" height="1212"></figure>
      <figure style="margin-top:48px"><img src="{{ asset('landing/img/wallet_demo_tab.jpg') }}" alt="Visitor wallet with transactions" loading="lazy" width="560" height="1212"></figure>
    </div>
  </div>
</section>

<section id="brand" class="band-alt" aria-labelledby="bTitle">
  <div class="wrap split">
    <div class="split-text">
      <p class="eyebrow" data-i18n="b_eyebrow">Your brand</p>
      <h2 id="bTitle" data-i18n="b_title">Configured, not coded</h2>
      <p data-i18n="b_lede">Change colors, logo and fonts in the branding editor and watch a live phone preview. The app checks every color for readability and adjusts shades that would be hard to read.</p>
      <div class="knobs">
        <span class="knob" data-i18n="k1">Colors and header style</span><span class="knob" data-i18n="k2">Logo and launch screen</span><span class="knob" data-i18n="k3">Arabic and English fonts</span><span class="knob" data-i18n="k4">Which features are on</span><span class="knob" data-i18n="k5">Tab order and home sections</span><span class="knob" data-i18n="k6">Top-up limits and bonuses</span><span class="knob" data-i18n="k7">Points rules and tiers</span><span class="knob" data-i18n="k8">Refund window and fees</span><span class="knob" data-i18n="k9">Country code and phone format</span>
      </div>
    </div>
    <figure class="frame" style="margin:0"><img src="{{ asset('landing/img/web_demo_branding.jpg') }}" alt="Branding editor with live phone preview" loading="lazy" width="1400" height="875"><figcaption class="frame-cap"><span data-i18n="cap_brand">Branding editor, sample organizer</span><span class="mono">/admin/branding</span></figcaption></figure>
  </div>
</section>

<section aria-labelledby="dTitle">
  <div class="wrap split rev">
    <div class="split-text">
      <p class="eyebrow" data-i18n="d_eyebrow">For your team</p>
      <h2 id="dTitle" data-i18n="d_title">The control room for the season</h2>
      <p data-i18n="d_lede">Revenue, tickets, top-ups and money held in wallets, live. Approve refunds, adjust wallets with a reason on record, and give each team member only the access they need.</p>
      <ul class="ticks">
        <li><span data-i18n="d_1"><b>Every rial accounted for.</b> An append-only ledger separates cash, promo credit and held deposits.</span></li>
        <li><span data-i18n="d_2"><b>Several organizers, kept apart.</b> Each organizer's events, wallets and staff stay separate, with one platform view on top.</span></li>
        <li><span data-i18n="d_3"><b>Arabic and English dashboard.</b> Right-to-left done properly, with dark mode.</span></li>
      </ul>
    </div>
    <figure class="frame" style="margin:0"><img src="{{ asset('landing/img/web_demo_dashboard.jpg') }}" alt="Admin dashboard with revenue tiles and activity chart" loading="lazy" width="1400" height="875"><figcaption class="frame-cap"><span data-i18n="cap_dash">Dashboard, sample organizer</span><span class="mono">/admin</span></figcaption></figure>
  </div>
</section>

<section class="band-alt" aria-labelledby="cTitle">
  <div class="wrap">
    <div class="sec-head">
      <p class="eyebrow" data-i18n="c_eyebrow">Where Mawsim fits</p>
      <h2 id="cTitle" data-i18n="c_title">Ticketing apps sell entry. Cashless kits handle stalls. Mawsim does both, in your name.</h2>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th scope="col" data-i18n="c_h0">What you need</th><th scope="col" data-i18n="c_h1">Ticketing marketplaces</th><th scope="col" data-i18n="c_h2">Cashless wristband systems</th><th scope="col" class="us">Mawsim</th></tr></thead>
        <tbody id="cmpBody"></tbody>
      </table>
    </div>
    <p class="cmp-note" data-i18n="c_note">Typical offerings in the GCC market, from our 2026 competitor review. Individual products vary.</p>
  </div>
</section>

<section aria-labelledby="lTitle">
  <div class="wrap">
    <div class="sec-head">
      <p class="eyebrow" data-i18n="l_eyebrow">Getting started</p>
      <h2 id="lTitle" data-i18n="l_title">From brand kit to opening night</h2>
    </div>
    <ol class="launch" style="margin:0;padding:0">
      <li><span class="k">1</span><h3 data-i18n="l1_t">Send your brand kit</h3><p data-i18n="l1_p">Logo, colors, fonts and the features you want switched on.</p></li>
      <li><span class="k">2</span><h3 data-i18n="l2_t">Load the season</h3><p data-i18n="l2_p">Events, prices, capacity, vendors and map points, all from the dashboard.</p></li>
      <li><span class="k">3</span><h3 data-i18n="l3_t">Your app in the stores</h3><p data-i18n="l3_p">A build with your name, icon and launch screen, on iOS and Android.</p></li>
      <li><span class="k">4</span><h3 data-i18n="l4_t">Run the season</h3><p data-i18n="l4_p">Gate scanning, vendor sales, refunds and daily totals, with support through the season.</p></li>
    </ol>
  </div>
</section>

<section id="plans" class="band-alt" aria-labelledby="pTitle">
  <div class="wrap">
    <div class="sec-head">
      <p class="eyebrow" data-i18n="pl_eyebrow">Plans</p>
      <h2 id="pTitle" data-i18n="pl_title">Pick the shape of your season</h2>
    </div>
    <div class="plans">
      <div class="plan"><h3 data-i18n="pl1_t">Season</h3><p class="who" data-i18n="pl1_w">One festival or event season.</p><ul><li data-i18n="pl1_a">Your branded app</li><li data-i18n="pl1_b">Tickets and gate scanning</li><li data-i18n="pl1_c">Wallet, top-ups and vendor payments</li><li data-i18n="pl1_d">Launch support</li></ul></div>
      <div class="plan feat"><h3 data-i18n="pl2_t">Organizer</h3><p class="who" data-i18n="pl2_w">Year-round events under one brand.</p><ul><li data-i18n="pl2_a">Everything in Season</li><li data-i18n="pl2_b">Points, tiers and top-up bonuses</li><li data-i18n="pl2_c">Cup, wristband and locker deposits</li><li data-i18n="pl2_d">Vendor onboarding and settlement reports</li></ul></div>
      <div class="plan"><h3 data-i18n="pl3_t">Governorate and enterprise</h3><p class="who" data-i18n="pl3_w">Many organizers or wilayats on one platform.</p><ul><li data-i18n="pl3_a">Separate brands and data per organizer</li><li data-i18n="pl3_b">Platform-wide view for your office</li><li data-i18n="pl3_c">Your own payment gateway account</li><li data-i18n="pl3_d">On-site operations support</li></ul></div>
    </div>
    <p class="price-line"><b data-i18n="price_b">Priced as a share of ticket and wallet volume, with a minimum.</b><span data-i18n="price_s">No fixed per-ticket fee, so a 0.500 OMR entry ticket still makes sense.</span></p>
  </div>
</section>

<section id="faq" aria-labelledby="fTitle">
  <div class="wrap">
    <div class="sec-head"><p class="eyebrow" data-i18n="f_eyebrow">Questions</p><h2 id="fTitle" data-i18n="f_title">What organizers ask us</h2></div>
    <div class="faq">
      <details><summary data-i18n="q1">Is the wallet regulated?</summary><p data-i18n="a1">Stored value in Oman falls under the Central Bank of Oman. Mawsim keeps wallet credit within your events and takes payments through a licensed bank gateway. We review the setup with each organizer and recommend a legal check before launch.</p></details>
      <details><summary data-i18n="q2">Can visitors get their money back?</summary><p data-i18n="a2">Yes. Unused cash can be refunded on request within the window you set, minus any fee you choose. Promo credit from bonuses or points is not refundable, and the app explains the difference before the visitor asks.</p></details>
      <details><summary data-i18n="q3">Do we get our own app in the stores?</summary><p data-i18n="a3">Yes. Each organizer gets a build with its own name, icon, colors and launch screen. Colors, logo and content changes after launch apply from the dashboard without a new release.</p></details>
      <details><summary data-i18n="q4">Can one office run several events or wilayats?</summary><p data-i18n="a4">Yes. Each organizer has its own events, wallets, vendors and staff, kept separate, and a platform admin can switch between them.</p></details>
      <details><summary data-i18n="q5">What do vendors need?</summary><p data-i18n="a5">A smartphone with the app. Staff sign in with their phone number and get vendor mode with a keypad, a scanner and today's totals.</p></details>
      <details><summary data-i18n="q6">Is it really Arabic-first?</summary><p data-i18n="a6">The app, the dashboard and the ticket pages are designed right-to-left first, with English alongside. Money uses Western digits to three decimals, the way OMR is written on receipts.</p></details>
    </div>
  </div>
</section>

<section id="demo" class="cta" aria-labelledby="ctaTitle">
  <div class="wrap">
    <div>
      <p class="eyebrow" data-i18n="cta_eyebrow">Book a demo</p>
      <h2 id="ctaTitle" style="margin-top:14px" data-i18n="cta_title">Bring Mawsim to your next season</h2>
      <p class="sub" data-i18n="cta_sub">Tell us about your event. We'll show the app in your colors and walk through a full visit, from top-up to refund.</p>
    </div>
    <form id="demoForm" method="POST" action="{{ route('landing.demo-request') }}" novalidate>
      @csrf
      <input type="hidden" name="locale" id="fLocale" value="{{ $locale }}">
      <input type="hidden" name="rendered_at" value="{{ $formTs }}">
      <div class="hp" aria-hidden="true"><label for="fWebsite">Website</label><input id="fWebsite" name="website" type="text" tabindex="-1" autocomplete="off"></div>
      <div class="field"@if($done) hidden @endif><label for="fName" data-i18n="fm_name">Your name</label><input id="fName" name="name" autocomplete="name" maxlength="120" value="{{ old('name') }}" required></div>
      <div class="field"@if($done) hidden @endif><label for="fOrg" data-i18n="fm_org">Organization</label><input id="fOrg" name="organization" autocomplete="organization" maxlength="160" value="{{ old('organization') }}" required></div>
      <div class="field"@if($done) hidden @endif><label for="fType" data-i18n="fm_type">Type of event</label><select id="fType" name="event_type">@foreach($eventTypes as $key => $label)<option value="{{ $key }}" @selected(old('event_type') === $key)>{{ $label }}</option>@endforeach</select></div>
      <div class="field"@if($done) hidden @endif><label for="fSize" data-i18n="fm_size">Expected visitors</label><select id="fSize" name="expected_visitors">@foreach($visitorRanges as $key => $label)<option value="{{ $key }}" @selected(old('expected_visitors') === $key)>{{ $label }}</option>@endforeach</select></div>
      <div class="field"@if($done) hidden @endif><label for="fWhen" data-i18n="fm_when">Season dates</label><input id="fWhen" name="season" maxlength="120" placeholder="Dec 2026 – Jan 2027" value="{{ old('season') }}"></div>
      <div class="field"@if($done) hidden @endif><label for="fContact" data-i18n="fm_contact">Phone or email</label><input id="fContact" name="contact" maxlength="190" autocomplete="email" value="{{ old('contact') }}" required></div>
      <div class="field full"@if($done) hidden @endif><label for="fNotes" data-i18n="fm_notes">Anything else</label><textarea id="fNotes" name="notes" maxlength="2000">{{ old('notes') }}</textarea></div>
      <p class="err" id="fErr" role="alert"@unless($formError) hidden @endunless>{{ $formError }}</p>
      <div class="form-foot" id="fFoot"@if($done) hidden @endif><small data-i18n="fm_small">Takes a minute. No commitment.</small><button class="btn btn-accent" type="submit" id="fSend" data-i18n="fm_send">Send my request</button></div>
      <div class="done" id="fDone" role="status"@unless($done) hidden @endunless>
        <b data-i18n="fm_done_t">Request received</b>
        <p style="color:var(--on-night-muted);font-size:14.5px" id="fDoneP" data-contact="{{ $done['contact'] ?? '' }}">@if($done){!! str_replace(e('__CONTACT__'), '<bdi dir="ltr">'.e($done['contact']).'</bdi>', e(__('landing.success', ['contact' => '__CONTACT__']))) !!}@endif</p>
        <pre id="fSummary">{{ $done['summary'] ?? '' }}</pre>
        <div class="row"><a class="chip-btn" href="{{ $home }}#demo" id="fEdit" data-i18n="fm_again">Send another request</a><button class="chip-btn" type="button" id="fCopy" data-i18n="fm_copy" hidden>Copy summary</button><span id="fCopied" style="font-size:13.5px;color:var(--on-night-muted)" hidden></span></div>
      </div>
    </form>
  </div>
</section>
</main>

<footer>
  <div class="wrap">
    <span class="brand"><b>Mawsim</b><span>موسم</span></span>
    <span data-i18n="ft_by">An events platform by AWJ Innovation, Oman.</span>
    <span class="ft-end"><a class="ft-signin" href="{{ url('/admin/login') }}" data-i18n="ft_signin">Staff sign in</a><span>© <span id="yr">{{ date('Y') }}</span></span></span>
  </div>
</footer>

<script src="{{ asset('landing/landing.js') }}?v={{ $v('landing.js') }}"></script>
</body>
</html>
