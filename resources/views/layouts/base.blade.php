<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5" />
    {{-- Per-page SEO block (title/description/robots/canonical/alternates/OG/Twitter/JSON-LD),
         serialized byte-for-byte by App\Domain\Publishing\Seo\SeoHead to match the legacy site. --}}
    {!! $seoHead !!}
    @unless ($noindex)
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
    @endunless

    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png" />
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png" />
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192x192.png" />
    <link rel="icon" type="image/png" sizes="512x512" href="/favicon-512x512.png" />
    <link rel="icon" sizes="any" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <meta name="theme-color" content="#0A1628" />
    <meta name="apple-mobile-web-app-title" content="NavyWeek" />
    <meta name="fo-verify" content="302816c4-18a5-490e-af7d-50bd26109a99" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    {{-- NavyWeek design system (tokens + chrome + components). Without this the pages
         render as unstyled HTML — see CLAUDE.md "Visual verification is part of done". --}}
    @vite(['resources/css/app.css'])

    {{-- Ahrefs Analytics --}}
    <script async src="https://analytics.ahrefs.com/analytics.js" data-key="cyBzsvylryte/RFYYCOQMg"></script>

    @include('partials.posthog')

    @stack('head')
  </head>
  <body>
    @include('partials.header')
    @yield('content')
    @include('partials.footer')
  </body>
</html>
