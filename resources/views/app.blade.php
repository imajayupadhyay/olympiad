<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $siteName = 'National Olympiad Hunt';
            $siteUrl = rtrim(config('app.url', url('/')), '/');
            $canonicalUrl = request()->is('/') ? $siteUrl.'/' : url()->current();
            $shareImage = $siteUrl.'/NEO_logo_horizontal_light.png';
            $indexablePublicPaths = ['/', '/marketing'];
            $isIndexable = in_array('/'.ltrim(request()->path(), '/'), $indexablePublicPaths, true);
            $isNoindex = ! $isIndexable;

            $seoTitle = 'Online Olympiad Exams for Class 1-12 in India | National Olympiad Hunt';
            $seoDescription = 'National Olympiad Hunt offers online olympiad exams for Class 1-12 students in India. Register for Maths, Science, English and GK olympiads, earn national ranks, medals and certificates.';
            $seoKeywords = 'online olympiad exams, olympiad exams for class 1 to 12, national olympiad India, maths olympiad, science olympiad, English olympiad, GK olympiad, olympiad registration, student competitions India';
            $structuredData = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'EducationalOrganization',
                        '@id' => $siteUrl.'/#organization',
                        'name' => $siteName,
                        'url' => $siteUrl.'/',
                        'logo' => $shareImage,
                        'description' => $seoDescription,
                        'sameAs' => [],
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => $siteUrl.'/#website',
                        'url' => $siteUrl.'/',
                        'name' => $siteName,
                        'publisher' => ['@id' => $siteUrl.'/#organization'],
                        'inLanguage' => 'en-IN',
                    ],
                    [
                        '@type' => 'WebPage',
                        '@id' => $siteUrl.'/#webpage',
                        'url' => $siteUrl.'/',
                        'name' => $seoTitle,
                        'description' => $seoDescription,
                        'isPartOf' => ['@id' => $siteUrl.'/#website'],
                        'about' => ['@id' => $siteUrl.'/#organization'],
                        'inLanguage' => 'en-IN',
                    ],
                ],
            ];
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ $seoTitle }}</title>
        <meta name="description" content="{{ $seoDescription }}" />
        <meta name="keywords" content="{{ $seoKeywords }}" />
        <meta name="robots" content="{{ $isNoindex ? 'noindex, nofollow, noarchive' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' }}" />
        <link rel="canonical" href="{{ $canonicalUrl }}" />

        <!-- Open Graph / Twitter site-wide defaults (per-page values set via SeoHead) -->
        <meta property="og:site_name" content="National Olympiad Hunt" />
        <meta property="og:type" content="website" />
        <meta property="og:locale" content="en_IN" />
        <meta property="og:title" content="{{ $seoTitle }}" />
        <meta property="og:description" content="{{ $seoDescription }}" />
        <meta property="og:url" content="{{ $canonicalUrl }}" />
        <meta property="og:image" content="{{ $shareImage }}" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $seoTitle }}" />
        <meta name="twitter:description" content="{{ $seoDescription }}" />
        <meta name="twitter:image" content="{{ $shareImage }}" />
        <meta name="theme-color" content="#EE6A2C" />
        @if ($isIndexable)
            <script type="application/ld+json">@json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
        @endif

        <!-- Favicon (gold "N" monogram — brand mark) -->
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&family=Rajdhani:wght@600&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;0,9..144,900;1,9..144,500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-body antialiased bg-bg text-text-main">
        @inertia
    </body>
</html>
