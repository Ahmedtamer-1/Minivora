<?php

namespace App\Modules\Seo\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Seo\Services\FeedService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves Google Merchant Center (RSS 2.0 XML) and Meta Catalog (TSV) product feeds. FR-SEO-16
 */
class FeedController extends Controller
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly FeedService $feeds,
    ) {}

    public function google(Request $request): Response
    {
        $store = $this->currentStore->getOrFail();
        $xml = $this->feeds->googleMerchantRss($store, $request->getSchemeAndHttpHost());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function meta(Request $request): Response
    {
        $store = $this->currentStore->getOrFail();
        $tsv = $this->feeds->metaCatalogTsv($store, $request->getSchemeAndHttpHost());

        return response($tsv, 200, [
            'Content-Type' => 'text/tab-separated-values; charset=UTF-8',
        ]);
    }
}
