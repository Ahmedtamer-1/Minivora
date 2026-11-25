<?php

namespace App\Modules\Seo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seo\Models\Redirect;
use App\Modules\Seo\Services\RedirectService;
use App\Modules\Seo\Services\SeoAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dashboard SEO management endpoints: redirects, 404 logs, and on-page SEO audit.
 * FR-SEO-19, FR-SEO-23, FR-SEO-24
 */
class SeoController extends Controller
{
    public function __construct(
        private readonly RedirectService $redirects,
        private readonly SeoAuditService $auditor,
    ) {}

    public function redirects(): JsonResponse
    {
        return response()->json([
            'redirects' => Redirect::query()->latest('id')->get(),
        ]);
    }

    public function storeRedirect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:500'],
            'to_path' => ['required', 'string', 'max:500', 'different:from_path'],
            'status_code' => ['nullable', 'integer', Rule::in(RedirectService::ALLOWED_STATUS_CODES)],
        ]);

        $redirect = $this->redirects->addManual(
            $data['from_path'],
            $data['to_path'],
            (int) ($data['status_code'] ?? 301),
        );

        return response()->json(['redirect' => $redirect], 201);
    }

    public function destroyRedirect(int $redirect): JsonResponse
    {
        $record = Redirect::query()->findOrFail($redirect);
        $record->delete();

        return response()->json(['deleted' => true]);
    }

    public function notFoundLogs(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 20)));

        return response()->json([
            'logs' => $this->redirects->topNotFound($limit)->values(),
        ]);
    }

    public function audit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:1000'],
            'h1_count' => ['nullable', 'integer', 'min:0'],
            'h1s' => ['nullable', 'array'],
            'h1s.*' => ['string'],
            'images' => ['nullable', 'array'],
            'content' => ['nullable', 'string'],
            'word_count' => ['nullable', 'integer', 'min:0'],
            'json_ld' => ['nullable'],
            'other_titles' => ['nullable', 'array'],
            'other_titles.*' => ['string'],
            'other_descriptions' => ['nullable', 'array'],
            'other_descriptions.*' => ['string'],
        ]);

        $result = $this->auditor->audit(
            $data,
            $data['other_titles'] ?? [],
            $data['other_descriptions'] ?? [],
        );

        return response()->json($result);
    }
}
