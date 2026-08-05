@extends('layouts.base')

{{-- Home landing page (/). A 1:1 content port of the legacy Home.tsx: hero, key facts,
     current/next stop, the 12-city schedule, mission, partners, map teaser, and FAQ.
     Head/JSON-LD (WebSite + Breadcrumb + 2 GovernmentOrg + schedule ItemList + FAQPage) is
     byte-locked by SeoHead + HomePageSchema; this body is a clean semantic rebuild (visual
     styling is a later slice, consistent with the other ported hub views). --}}
@php
    use App\Domain\Publishing\Support\PagePaths;

    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\NavyWeekEvent> $events */
    /** @var \App\Domain\Pillars\Models\NavyWeekEvent|null $activeEvent */
    /** @var \App\Domain\Pillars\Models\NavyWeekEvent|null $currentOrNext */
    /** @var int $firstTimeCount */
    // Date ranges use NavyWeekEvent::dateRangeLabel() — the single formatter shared with
    // the city JSON-LD, so the home schedule never drifts from the per-city pages. City
    // links go through PagePaths (same knob the schedule ItemList uses), so the visible
    // links and the JSON-LD stay in lockstep if the /city/ prefix ever changes.
@endphp

@section('content')
    <main class="home-page">
        {{-- Full-bleed hero: photo at 20% opacity under a 40px grid overlay and a
             bottom fade to --navy, with the copy centred on top. Ported from
             src/page-views/Home.tsx. The legacy home has no breadcrumb. --}}
        <section class="home-hero" aria-label="Navy Week 2026 hero">
            <picture>
                <source type="image/avif" srcset="/images/hero-navy-week-704.avif 704w, /images/hero-navy-week-1408.avif 1408w" sizes="100vw">
                <source type="image/webp" srcset="/images/hero-navy-week-704.webp 704w, /images/hero-navy-week-1408.webp 1408w" sizes="100vw">
                <img class="home-hero-img" src="/images/hero-navy-week.png" width="1280" height="720"
                     loading="eager" decoding="async" fetchpriority="high"
                     alt="U.S. Navy destroyer at sea during sunset with American flag — Navy Week 2026 hero image">
            </picture>
            <div class="home-hero-grid" aria-hidden="true"></div>
            <div class="home-hero-fade" aria-hidden="true"></div>

            <div class="home-hero-body">
                <div class="home-hero-eyebrow">// ROAD TRIP TO 250</div>
                <h1>NAVY WEEK 2026</h1>
                <p class="home-hero-lede">Bringing the Navy to your community. Experience the pride, professionalism, and equipment of the United States Navy in 12 cities across America.</p>
                <p class="home-hero-sub">From Blue Angels flight demonstrations and Leap Frogs parachute jumps to Navy Band concerts and hands-on STEM exhibits, Navy Week 2026 offers free, family-friendly events in cities from coast to coast. Now in its 22nd year, the program connects Americans with the sailors, technology, and missions that keep the nation safe.</p>
                <div class="home-hero-cta">
                    <a class="home-cta-primary" href="/schedule/">View Full Schedule <span aria-hidden="true">&rarr;</span></a>
                    <a class="home-cta-secondary" href="/map/">See the Map <span aria-hidden="true">&#9679;</span></a>
                </div>
            </div>
        </section>

        {{-- LLM-friendly key facts --}}
        <section class="home-key-facts" aria-label="Navy Week 2026 key facts">
            <h2>Navy Week 2026 — Key Facts</h2>
            <dl>
                <dt>Program</dt>
                <dd>U.S. Navy Week — the Navy’s flagship community outreach program</dd>
                <dt>Operator</dt>
                <dd>Navy Office of Community Outreach (NAVCO), Millington, Tennessee</dd>
                <dt>Year</dt>
                <dd>2026 — "Road Trip to 250" tour</dd>
                <dt>Host cities</dt>
                <dd>{{ $events->count() }} cities ({{ $firstTimeCount }} first-time locations)</dd>
                <dt>Duration</dt>
                <dd>About one week per city, January through November 2026</dd>
                <dt>Cost</dt>
                <dd>All official Navy Week events are free and open to the public</dd>
                <dt>Personnel per city</dt>
                <dd>50–100 Sailors deployed</dd>
                <dt>Program started</dt>
                <dd>2005</dd>
            </dl>
            <p class="source">Source:
                <a href="https://outreach.navy.mil/Navy-Weeks/" target="_blank" rel="noopener noreferrer">outreach.navy.mil/Navy-Weeks</a>
            </p>
        </section>

        {{-- Current or next stop --}}
        @if ($currentOrNext)
            <section class="home-next-up" aria-label="Current or next Navy Week event">
                <p class="kicker">{{ $activeEvent ? 'Happening Now' : 'Next Stop' }}</p>
                <p class="next-city">{{ $currentOrNext->city }}, {{ $currentOrNext->state_abbr }}</p>
                <p class="next-dates">{{ $currentOrNext->dateRangeLabel() }}</p>
                @if ($activeEvent)
                    <p class="live-badge">Live This Week</p>
                @endif
                <a class="next-link" href="{{ PagePaths::child('navy_week_cities', $currentOrNext->slug) }}">View Details</a>
            </section>
        @endif

        {{-- 2026 schedule --}}
        <section id="schedule" class="home-schedule" aria-label="2026 Navy Week schedule preview">
            <h2>2026 SCHEDULE</h2>
            <p class="section-sub">12 cities. January through November.</p>
            @if ($events->isEmpty())
                <p class="empty-state">The 2026 schedule is coming soon.</p>
            @else
                <ul class="schedule-list">
                    @foreach ($events as $event)
                        <li class="schedule-card">
                            <a href="{{ PagePaths::child('navy_week_cities', $event->slug) }}">
                                <span class="city-name">{{ $event->city }}, {{ $event->state_abbr }}</span>
                                <span class="city-dates">{{ $event->dateRangeLabel() }}</span>
                                <span class="city-anchor">{{ $event->anchor_event }}</span>
                                <span class="city-status">{{ $event->status->label() }}</span>
                                @if ($event->first_time)
                                    <span class="city-badge">{{ $event->first_time_badge ?: 'First-Time Host' }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Our mission --}}
        <section class="home-mission" aria-label="Our mission">
            <p class="eyebrow">// Navy Office of Community Outreach</p>
            <h2>OUR MISSION</h2>
            <blockquote>
                <p>The Navy Week program is the United States Navy's flagship community outreach effort, bringing Sailors, ships, aircraft, and interactive experiences to cities across America without a significant Navy presence — showing Americans why a strong maritime force is vital to national security and the American way of life.</p>
                <footer>Managed by the Navy Office of Community Outreach (NAVCO), Millington, TN — Director: Cmdr. Julie Holland</footer>
            </blockquote>
            <dl class="mission-stats">
                <dt>300+</dt>
                <dd>Navy Weeks Conducted</dd>
                <dt>100+</dt>
                <dd>U.S. Markets Visited</dd>
                <dt>140M+</dt>
                <dd>Americans Reached Annually</dd>
                <dt>20+</dt>
                <dd>Years of Service</dd>
            </dl>
            <h3>WHAT IS NAVY WEEK?</h3>
            <p>Since 2005, the Navy Week program has served as the Navy's principal outreach effort in areas of the country without a significant Navy presence. Each week deploys 50 to 100 Sailors to a single city for about a week of community engagement — from Blue Angels flight demonstrations to Navy Band concerts, Leap Frogs parachute jumps, and hands-on STEM exhibits.</p>
            <p>All official Navy Week events are free and open to the public, making it one of the most accessible military outreach programs in the nation. Each stop generates 75 to 100 community events, reaching schools, civic organizations, veterans groups, and the general public.</p>
            <p>In 2026, the Navy Week tour celebrates America's 250th birthday with the "Road Trip to 250" theme, visiting 12 cities — including eight first-time Navy Week locations. From the Rio Grande Valley in January to Flagstaff in November, each stop brings the Navy's story of service, sacrifice, and readiness directly to the American people.</p>
            <p>
                <a href="https://outreach.navy.mil/Navy-Weeks/" target="_blank" rel="noopener noreferrer">Learn More at NAVCO</a>
            </p>
        </section>

        {{-- Our partners --}}
        <section id="partners" class="home-partners" aria-label="Our partners">
            <p class="eyebrow">// Trusted Partners</p>
            <h2>OUR PARTNERS</h2>
            <div class="partner-card">
                <p class="partner-name">CertaPet</p>
                <p class="partner-role">
                    <a href="https://www.certapet.com" target="_blank" rel="noopener noreferrer">ESA Letters</a>
                    · Licensed Telehealth Evaluations
                </p>
                <p>CertaPet is a telehealth platform that connects people — including service members, veterans, and military families — with licensed mental health professionals for emotional support animal (ESA) letter evaluations. If a clinician determines an ESA is right for you, CertaPet issues a legitimate ESA letter that meets federal housing requirements. Learn more at
                    <a href="https://www.certapet.com" target="_blank" rel="noopener noreferrer">www.certapet.com</a>.
                </p>
                <p>ESA letters help qualifying individuals live with their support animal in housing with no-pet policies and without pet fees — a benefit many veterans managing PTSD, anxiety, or depression rely on.</p>
            </div>
            <p class="source">NavyWeek.org partners with select services relevant to the military and veteran community.</p>
        </section>

        {{-- Map teaser --}}
        <section id="map" class="home-map" aria-label="Route map preview">
            <h2>ROUTE MAP</h2>
            <p class="section-sub">Explore the nationwide schedule.</p>
            <a class="btn btn-primary" href="/map/">View Interactive Map</a>
        </section>

        {{-- FAQ --}}
        @if ($page->faqs->isNotEmpty())
            <section id="faq" class="home-faqs" aria-label="Frequently asked questions">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                <dl>
                    @foreach ($page->faqs as $faq)
                        <dt><h3>{{ $faq->question }}</h3></dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif

        <p class="home-attribution">Data sourced from
            <a href="https://outreach.navy.mil/Navy-Weeks/" target="_blank" rel="noopener noreferrer">outreach.navy.mil</a>.
        </p>
    </main>
@endsection
