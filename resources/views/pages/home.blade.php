@extends('layouts.base')

{{-- Home landing page (/). A 1:1 port of the legacy src/page-views/Home.tsx: hero,
     key facts, current/next stop, the 12-city EventCard grid, mission, partners, the
     route-map teaser and the FAQ accordion. Head/JSON-LD (WebSite + Breadcrumb +
     2 GovernmentOrg + schedule ItemList + FAQPage) is byte-locked by SeoHead +
     HomePageSchema. --}}
@php
    use App\Domain\Pillars\Enums\NavyWeekStatus;
    use App\Domain\Publishing\Support\PagePaths;
    use App\Domain\Publishing\Support\UsMapGeometry;

    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\NavyWeekEvent> $events */
    /** @var \App\Domain\Pillars\Models\NavyWeekEvent|null $activeEvent */
    /** @var \App\Domain\Pillars\Models\NavyWeekEvent|null $currentOrNext */
    /** @var int $firstTimeCount */
    // formatShortDateRange (src/data/data.ts): zero-padded days, month repeated only
    // when the range crosses a month boundary.
    $shortRange = static function ($e): string {
        $s = $e->start_date;
        $t = $e->end_date;

        return $s->format('M') === $t->format('M')
            ? $s->format('M d').' – '.$t->format('d, Y')
            : $s->format('M d').' – '.$t->format('M d, Y');
    };
    $statusLabel = static fn ($e): string => $e->status === NavyWeekStatus::Active
        ? 'ACTIVE NOW'
        : mb_strtoupper($e->status->label());
    $isPast = static fn ($e): bool => $e->status === NavyWeekStatus::Completed;
    // lucide icons — the legacy CTAs use the icon, not a text glyph.
    $arrow14 = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>';
    $arrow12 = str_replace('width="14" height="14"', 'width="12" height="12"', $arrow14);
    $mapPin = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>';
    // The KeyFacts props from Home.tsx, with the counts read live from the pillar.
    $homeKeyFacts = [
        'title' => 'Navy Week 2026 — Key Facts',
        'facts' => [
            ['label' => 'Program', 'value' => 'U.S. Navy Week — the Navy’s flagship community outreach program'],
            ['label' => 'Operator', 'value' => 'Navy Office of Community Outreach (NAVCO), Millington, Tennessee'],
            ['label' => 'Year', 'value' => '2026 — "Road Trip to 250" tour'],
            ['label' => 'Host cities', 'value' => $events->count().' cities ('.$firstTimeCount.' first-time locations)'],
            ['label' => 'Duration', 'value' => 'About one week per city, January through November 2026'],
            ['label' => 'Cost', 'value' => 'All official Navy Week events are free and open to the public'],
            ['label' => 'Personnel per city', 'value' => '50–100 Sailors deployed'],
            ['label' => 'Program started', 'value' => '2005'],
        ],
        'source' => ['label' => 'outreach.navy.mil/Navy-Weeks', 'url' => 'https://outreach.navy.mil/Navy-Weeks/'],
        'lastVerified' => 'July 12, 2026',
    ];
    $pins = UsMapGeometry::pinPositions();
    $inset = UsMapGeometry::insetLabels();
    $vbW = UsMapGeometry::VIEWBOX_WIDTH;
    $vbH = UsMapGeometry::VIEWBOX_HEIGHT;
    $pinFill = ['active' => '#2E9E5E', 'completed' => '#8C8C7A', 'upcoming' => '#C9A84C'];
@endphp

@section('content')
    <main class="home-page">
        {{-- Full-bleed hero: photo at 20% opacity under a 40px grid overlay and a
             bottom fade to --navy, with the copy centred on top. The legacy home has
             no breadcrumb. --}}
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
                    <a class="home-cta-primary" href="/schedule/">View Full Schedule {!! $arrow14 !!}</a>
                    <a class="home-cta-secondary" href="/map/">See the Map {!! $mapPin !!}</a>
                </div>
            </div>
        </section>

        {{-- LLM-friendly key facts --}}
        <section class="home-keyfacts" aria-label="Navy Week 2026 key facts">
            @include('partials.trust.key-facts', ['keyFacts' => $homeKeyFacts])
        </section>

        {{-- Current or next stop --}}
        @if ($currentOrNext)
            @php
                $daysUntil = max(0, (int) ceil(now()->diffInSeconds($currentOrNext->start_date->copy()->startOfDay(), false) / 86400));
            @endphp
            <section class="home-next" aria-label="Current or next Navy Week event">
                <div class="home-next-inner">
                    <div class="home-next-main">
                        <div class="status-badge">
                            <span class="status-badge-pill is-{{ $currentOrNext->status->value }}">{{ $statusLabel($currentOrNext) }}</span>
                            @if ($currentOrNext->first_time || $currentOrNext->first_time_badge)
                                <span class="status-badge-pill is-firsttime">{{ $currentOrNext->first_time ? 'FIRST-TIME HOST' : mb_strtoupper($currentOrNext->first_time_badge) }}</span>
                            @endif
                        </div>
                        <div>
                            <div class="home-next-kicker">{{ $activeEvent ? 'HAPPENING NOW' : 'NEXT STOP' }}</div>
                            <div class="home-next-city">{{ $currentOrNext->city }}, {{ $currentOrNext->state_abbr }}</div>
                            <div class="home-next-dates">{{ $shortRange($currentOrNext) }}</div>
                        </div>
                        @if ($activeEvent)
                            <div class="home-next-live">
                                <span class="home-next-live-dot" aria-hidden="true"></span>
                                <span>Live This Week</span>
                            </div>
                        @else
                            <div class="home-next-countdown">
                                <span class="home-next-days">{{ $daysUntil }}</span>
                                <span class="home-next-days-label">days away</span>
                            </div>
                        @endif
                    </div>
                    <a class="home-next-link" href="{{ PagePaths::child('navy_week_cities', $currentOrNext->slug) }}">View Details {!! $arrow14 !!}</a>
                </div>
            </section>
        @endif

        {{-- 2026 schedule --}}
        <section id="schedule" class="home-schedule" aria-label="2026 Navy Week schedule preview">
            <div class="home-schedule-head">
                <div>
                    <h2>2026 SCHEDULE</h2>
                    <p class="home-section-sub">12 CITIES. JANUARY THROUGH NOVEMBER.</p>
                </div>
            </div>

            <div class="schedule-grid">
                @foreach ($events as $event)
                    <article class="event-card status-{{ $event->status->value }} @if ($isPast($event)) is-past @endif">
                        <div class="event-card-dates">{{ $shortRange($event) }}</div>
                        <div class="event-card-city">{{ $event->city }}</div>
                        <div class="event-card-state">{{ $event->state }}@if ($event->first_time) · First Time Host @elseif ($event->first_time_badge) · {{ $event->first_time_badge }}@endif</div>
                        <div class="event-card-anchor">Anchor event: {{ $event->anchor_event }}</div>
                        <div class="event-card-foot">
                            <a href="{{ PagePaths::child('navy_week_cities', $event->slug) }}">Learn More {!! $arrow12 !!}</a>
                            <div class="status-badge">
                                <span class="status-badge-pill is-{{ $event->status->value }}">{{ $statusLabel($event) }}</span>
                                @if ($event->first_time || $event->first_time_badge)
                                    <span class="status-badge-pill is-firsttime">{{ $event->first_time ? 'FIRST-TIME HOST' : mb_strtoupper($event->first_time_badge) }}</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- Our mission --}}
        <section class="home-mission" aria-label="Our mission">
            <div class="home-inner">
                <div class="home-band-head">
                    <div class="home-band-eyebrow">// Navy Office of Community Outreach</div>
                    <h2>OUR MISSION</h2>
                </div>

                <blockquote class="home-quote">
                    <p>The Navy Week program is the United States Navy's flagship community outreach effort, bringing Sailors, ships, aircraft, and interactive experiences to cities across America without a significant Navy presence — showing Americans why a strong maritime force is vital to national security and the American way of life.</p>
                    <footer>— Managed by the Navy Office of Community Outreach (NAVCO), Millington, TN<br>Director: Cmdr. Julie Holland</footer>
                </blockquote>

                <div class="home-stats">
                    <div class="home-stat"><div class="home-stat-value">300+</div><div class="home-stat-label">Navy Weeks Conducted</div></div>
                    <div class="home-stat"><div class="home-stat-value">100+</div><div class="home-stat-label">U.S. Markets Visited</div></div>
                    <div class="home-stat"><div class="home-stat-value">140M+</div><div class="home-stat-label">Americans Reached Annually</div></div>
                    <div class="home-stat"><div class="home-stat-value">20+</div><div class="home-stat-label">Years of Service</div></div>
                </div>

                <p class="home-stats-source">Source: Navy Office of Community Outreach (NAVCO)</p>

                <div class="home-mission-grid">
                    <div class="home-mission-media">
                        <picture>
                            <source type="image/avif" srcset="/images/navy-community-outreach-704.avif 704w, /images/navy-community-outreach-1408.avif 1408w" sizes="(max-width: 768px) 100vw, 50vw">
                            <source type="image/webp" srcset="/images/navy-community-outreach-704.webp 704w, /images/navy-community-outreach-1408.webp 1408w" sizes="(max-width: 768px) 100vw, 50vw">
                            <img class="home-mission-img" src="/images/navy-community-outreach.png" width="640" height="480" loading="lazy" decoding="async"
                                 alt="U.S. Navy sailors engaging with families and children at a Navy Week community outreach event">
                        </picture>
                        <div class="home-mission-figures">
                            <div><div class="home-figure-value">50–100</div><div class="home-figure-label">Sailors per City</div></div>
                            <div><div class="home-figure-value">75–100</div><div class="home-figure-label">Events per Week</div></div>
                        </div>
                    </div>

                    <div class="home-mission-copy">
                        <h3>WHAT IS NAVY WEEK?</h3>
                        <p class="home-mission-lede">Since 2005, the Navy Week program has served as the Navy's principal outreach effort in areas of the country without a significant Navy presence. Each week deploys 50 to 100 Sailors to a single city for about a week of community engagement — from Blue Angels flight demonstrations to Navy Band concerts, Leap Frogs parachute jumps, and hands-on STEM exhibits.</p>
                        <p>All official Navy Week events are free and open to the public, making it one of the most accessible military outreach programs in the nation. Each stop generates 75 to 100 community events, reaching schools, civic organizations, veterans groups, and the general public.</p>
                        <p class="home-mission-last">In 2026, the Navy Week tour celebrates America's 250th birthday with the <strong>"Road Trip to 250"</strong> theme, visiting 12 cities — including eight first-time Navy Week locations. From the Rio Grande Valley in January to Flagstaff in November, each stop brings the Navy's story of service, sacrifice, and readiness directly to the American people.</p>
                        <a class="home-mission-link" href="https://outreach.navy.mil/Navy-Weeks/" target="_blank" rel="noopener noreferrer">Learn More at NAVCO {!! $arrow14 !!}</a>
                    </div>
                </div>
            </div>
        </section>

        {{-- Our partners --}}
        <section id="partners" class="home-partners" aria-label="Our partners">
            <div class="home-inner">
                <div class="home-band-head">
                    <div class="home-band-eyebrow">// Trusted Partners</div>
                    <h2>OUR PARTNERS</h2>
                </div>

                <div class="home-partner-card">
                    <div class="home-partner-name">CERTAPET</div>
                    <div class="home-partner-role">
                        <a href="https://www.certapet.com" target="_blank" rel="noopener noreferrer">ESA Letters</a> · Licensed Telehealth Evaluations
                    </div>
                    <p class="home-partner-body">Certapet is a telehealth platform that connects people — including service members, veterans, and military families — with licensed mental health professionals for emotional support animal (ESA) letter evaluations. If a clinician determines an ESA is right for you, Certapet issues a legitimate ESA letter that meets federal housing requirements. Learn more at <a href="https://www.certapet.com" target="_blank" rel="noopener noreferrer">www.certapet.com</a>.</p>
                    <p class="home-partner-note">ESA letters help qualifying individuals live with their support animal in housing with no-pet policies and without pet fees — a benefit many veterans managing PTSD, anxiety, or depression rely on.</p>
                </div>

                <p class="home-partner-source">NavyWeek.org partners with select services relevant to the military and veteran community.</p>
            </div>
        </section>

        {{-- Map teaser --}}
        <section id="map" class="home-map" aria-label="Route map preview">
            <h2>ROUTE MAP</h2>
            <p class="home-section-sub">EXPLORE THE NATIONWIDE SCHEDULE</p>

            <a class="home-map-teaser" href="/map/">
                <svg class="map-svg is-readonly" viewBox="0 0 {{ $vbW }} {{ $vbH }}" preserveAspectRatio="xMidYMid meet"
                     role="img" aria-label="Map of the United States showing all {{ $events->count() }} Navy Week 2026 tour stops">
                    <g class="map-svg-grid">
                        @for ($i = 0; $i < (int) ceil($vbW / 40) + 1; $i++)
                            <line x1="{{ $i * 40 }}" y1="0" x2="{{ $i * 40 }}" y2="{{ $vbH }}" stroke="#FAFAF8" stroke-width="0.5" />
                        @endfor
                        @for ($i = 0; $i < (int) ceil($vbH / 40) + 1; $i++)
                            <line x1="0" y1="{{ $i * 40 }}" x2="{{ $vbW }}" y2="{{ $i * 40 }}" stroke="#FAFAF8" stroke-width="0.5" />
                        @endfor
                    </g>
                    <path d="{{ UsMapGeometry::NATION_PATH }}" fill="rgba(21,35,64,0.85)" stroke="rgba(201,168,76,0.35)"
                          stroke-width="1.25" stroke-linejoin="round" stroke-linecap="round" />
                    <path d="{{ UsMapGeometry::STATE_BORDERS_PATH }}" fill="none" stroke="rgba(201,168,76,0.16)"
                          stroke-width="0.6" stroke-linejoin="round" stroke-linecap="round" />
                    <text class="map-svg-inset" x="{{ $inset['alaska']['x'] }}" y="{{ $inset['alaska']['y'] + 4 }}" text-anchor="middle">ALASKA</text>
                    <text class="map-svg-inset" x="{{ $inset['hawaii']['x'] }}" y="{{ $inset['hawaii']['y'] + 22 }}" text-anchor="middle">HAWAII</text>
                    @foreach ($events as $event)
                        @continue(! isset($pins[$event->slug]))
                        @php
                            $pos = $pins[$event->slug];
                            $offset = UsMapGeometry::labelOffset($event->city);
                            $status = $event->status->value;
                            $radius = $status === 'active' ? 7 : 5;
                        @endphp
                        <g class="map-pin is-{{ $status }}">
                            @if ($status === 'active')
                                <circle cx="{{ $pos['x'] }}" cy="{{ $pos['y'] }}" r="14" fill="none" stroke="{{ $pinFill[$status] }}" stroke-width="1.5" opacity="0.5">
                                    <animate attributeName="r" values="8;20;8" dur="2s" repeatCount="indefinite" />
                                    <animate attributeName="opacity" values="0.6;0;0.6" dur="2s" repeatCount="indefinite" />
                                </circle>
                                <circle cx="{{ $pos['x'] }}" cy="{{ $pos['y'] }}" r="10" fill="none" stroke="{{ $pinFill[$status] }}" stroke-width="0.8" opacity="0.3">
                                    <animate attributeName="r" values="6;15;6" dur="2s" repeatCount="indefinite" begin="0.4s" />
                                    <animate attributeName="opacity" values="0.4;0;0.4" dur="2s" repeatCount="indefinite" begin="0.4s" />
                                </circle>
                            @endif
                            <circle class="map-pin-hit" cx="{{ $pos['x'] }}" cy="{{ $pos['y'] }}" r="{{ $radius }}" fill="transparent" />
                            <circle class="map-pin-dot" cx="{{ $pos['x'] }}" cy="{{ $pos['y'] }}" r="{{ $radius }}" fill="{{ $pinFill[$status] }}"
                                    stroke="rgba(10,22,40,0.8)" stroke-width="1.5" />
                            <text class="map-pin-label" x="{{ $pos['x'] + $offset['dx'] }}" y="{{ $pos['y'] + $offset['dy'] }}" text-anchor="{{ $offset['anchor'] }}">{{ $event->city }}</text>
                            @if ($status === 'active')
                                <text class="map-pin-active" x="{{ $pos['x'] + $offset['dx'] }}" y="{{ $pos['y'] + $offset['dy'] + 13 }}" text-anchor="{{ $offset['anchor'] }}">ACTIVE NOW</text>
                            @endif
                        </g>
                    @endforeach
                </svg>
            </a>

            <div class="home-map-cta">
                <a class="home-cta-primary" href="/map/">View Interactive Map {!! $arrow14 !!}</a>
            </div>
        </section>

        {{-- FAQ --}}
        @if ($page->faqs->isNotEmpty())
            <section id="faq" class="home-faq" aria-label="Frequently asked questions">
                <div class="home-faq-inner">
                    <h2>FREQUENTLY ASKED QUESTIONS</h2>
                    <p class="home-section-sub">EVERYTHING YOU NEED TO KNOW ABOUT NAVY WEEK 2026</p>
                    <div class="home-faq-list">
                        @foreach ($page->faqs as $faq)
                            <div class="home-faq-item">
                                <h3>
                                    <button type="button" class="home-faq-q" aria-expanded="false" aria-controls="home-faq-a-{{ $loop->index }}">
                                        <span>{{ $faq->question }}</span>
                                        <svg class="home-faq-chevron-down" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--nw-gray)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                                        <svg class="home-faq-chevron-up" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"></path></svg>
                                    </button>
                                </h3>
                                <div class="home-faq-a" id="home-faq-a-{{ $loop->index }}">
                                    <div>{{ $faq->answer }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <div class="home-attribution">
            <p>Data sourced from <a href="https://outreach.navy.mil/Navy-Weeks/" target="_blank" rel="noopener noreferrer">outreach.navy.mil</a> — last verified July 12, 2026</p>
        </div>
    </main>

    @push('scripts')
        <script>
            // FAQ accordion, ported from the legacy Home.tsx <FAQAccordion>: one open
            // panel at a time, max-height transition. Answers are in the DOM (and the
            // FAQPage JSON-LD) whether or not JS runs.
            (function () {
                var items = [].slice.call(document.querySelectorAll('.home-faq-item'));
                items.forEach(function (item) {
                    var btn = item.querySelector('.home-faq-q');
                    if (!btn) { return; }
                    btn.addEventListener('click', function () {
                        var open = item.classList.contains('is-open');
                        items.forEach(function (o) {
                            o.classList.remove('is-open');
                            var b = o.querySelector('.home-faq-q');
                            if (b) { b.setAttribute('aria-expanded', 'false'); }
                        });
                        if (!open) {
                            item.classList.add('is-open');
                            btn.setAttribute('aria-expanded', 'true');
                        }
                    });
                });
            })();
        </script>
    @endpush
@endsection
