<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\Subscriber;
use App\Modules\Marketing\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Newsletter subscription capture, listing, and UTF-8 BOM CSV export. FR-MKT-05
 */
class SubscriberController extends Controller
{
    public function __construct(private readonly NewsletterService $newsletter) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'subscribers' => Subscriber::query()->latest('subscribed_at')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);

        $subscriber = $this->newsletter->subscribe(
            $data['email'],
            $data['name'] ?? null,
            $data['source'] ?? 'storefront',
        );

        return response()->json(['subscriber' => $subscriber], 201);
    }

    public function export(): Response
    {
        return response($this->newsletter->exportCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="subscribers.csv"',
        ]);
    }
}
