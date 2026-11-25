<?php

namespace App\Modules\Seo\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Seo\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves per-store robots.txt at /robots.txt. FR-SEO-06
 */
class RobotsController extends Controller
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly SitemapService $sitemaps,
    ) {}

    public function index(Request $request): Response
    {
        $content = $this->sitemaps->robotsTxt(
            $request->getSchemeAndHttpHost(),
            $this->currentStore->get(),
        );

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
