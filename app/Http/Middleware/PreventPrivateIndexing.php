<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventPrivateIndexing
{
    /**
     * Only routes in this list are meant to be indexed. Everything else gets an
     * X-Robots-Tag header so private/auth/app routes stay out of search results
     * even if a crawler discovers a URL outside the sitemap.
     */
    private const INDEXABLE_PATHS = [
        '/',
        '/marketing',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        if ($this->shouldNoindex($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }

    private function shouldNoindex(Request $request): bool
    {
        $path = '/'.ltrim($request->path(), '/');

        if (in_array($path, self::INDEXABLE_PATHS, true)) {
            return false;
        }

        return ! in_array($path, ['/robots.txt', '/sitemap.xml'], true);
    }
}
