<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\AuditLog\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\ContactMessage;
use App\Modules\Catalog\Models\StaticPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Static informational pages (about, contact, privacy, terms, shipping, exchange, returns)
 * and the merchant contact-message inbox. FR-CAT-11, FR-CAT-13
 */
class StaticPageController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(): JsonResponse
    {
        $pages = StaticPage::query()->orderBy('slug')->get();

        return response()->json([
            'pages' => $pages,
            'allowed_slugs' => StaticPage::ALLOWED_SLUGS,
        ]);
    }

    /**
     * Create or update one of the store's static pages. FR-CAT-11
     */
    public function upsert(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        if (! in_array($slug, StaticPage::ALLOWED_SLUGS, true)) {
            throw ValidationException::withMessages([
                'slug' => 'Invalid static page slug.',
            ]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'title_ar' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:20000'],
            'body_ar' => ['nullable', 'string', 'max:20000'],
        ]);

        $page = StaticPage::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'title' => trim($data['title']),
                'title_ar' => isset($data['title_ar']) ? trim((string) $data['title_ar']) : null,
                'body' => $data['body'] ?? null,
                'body_ar' => $data['body_ar'] ?? null,
            ],
        );

        $this->activity->log('static_page.saved', $page, ['slug' => $page->slug]);

        if ($request->expectsJson()) {
            return response()->json(['page' => $page]);
        }

        return back()->with('success', __('pages.saved'));
    }

    /**
     * List incoming shopper contact messages with unread count. FR-CAT-13
     */
    public function inbox(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        return response()->json([
            'messages' => ContactMessage::query()->latest('id')->paginate($perPage),
            'unread_count' => ContactMessage::query()->unread()->count(),
        ]);
    }

    /**
     * Mark a contact message as read. FR-CAT-13
     */
    public function markRead(Request $request, int $message): JsonResponse|RedirectResponse
    {
        $message = ContactMessage::query()->findOrFail($message);

        if ($message->read_at === null) {
            $message->read_at = Carbon::now();
            $message->save();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', __('messages.marked_read'));
    }
}
