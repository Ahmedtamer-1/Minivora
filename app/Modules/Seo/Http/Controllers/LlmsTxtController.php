<?php

namespace App\Modules\Seo\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Seo\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves per-store llms.txt Markdown summary for AI search tools. FR-SEO-15
 */
class LlmsTxtController extends Controller
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly SitemapService $sitemaps,
    ) {}

    public function index(Request $request): Response
    {
        $store = $this->currentStore->getOrFail();
        $content = $this->sitemaps->llmsTxt($store, $request->getSchemeAndHttpHost());

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
