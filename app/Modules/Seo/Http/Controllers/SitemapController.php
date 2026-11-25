<?php

namespace App\Modules\Seo\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Seo\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves per-store XML sitemap at /sitemap.xml. FR-SEO-05
 */
class SitemapController extends Controller
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly SitemapService $sitemaps,
    ) {}

    public function index(Request $request): Response
    {
        $store = $this->currentStore->getOrFail();
        $xml = $this->sitemaps->generateXml($store, $request->getSchemeAndHttpHost());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
