<?php


namespace App\Http\Controllers;

use App\Support\ArabicDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = $request->validate([
            'read' => ['nullable', Rule::in(['read', 'unread'])],
            'type' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $query = $user->notifications();

        if (($filters['read'] ?? null) === 'unread') {
            $query->whereNull('read_at');
        } elseif (($filters['read'] ?? null) === 'read') {
            $query->whereNotNull('read_at');
        }

        if (! empty($filters['type'])) {
            $query->where('data->type', $filters['type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($query) use ($search): void {
                $query->where('data->title', 'like', "%{$search}%")
                    ->orWhere('data->message', 'like', "%{$search}%");
            });
        }

        $notifications = $query->latest()->paginate(20)->withQueryString();
        $unreadCount = $user->unreadNotifications()->count();

        /*
         * Keep notification type discovery database-agnostic.
         * Tests run on SQLite while production may run on MySQL, so avoid
         * MySQL-only JSON_UNQUOTE / JSON_EXTRACT expressions here.
         */
        $types = $user->notifications()
            ->reorder()
            ->get(['data'])
            ->map(function (DatabaseNotification $notification): ?string {
                $data = $notification->data;

                return is_array($data)
                    ? ($data['type'] ?? null)
                    : null;
            })
            ->filter()
            ->unique()
            ->values();

        return view('notifications.index', compact(
            'notifications',
            'unreadCount',
            'types'
        ));
    }

    public function checkNew(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'after_id' => ['nullable', 'uuid'],
        ]);

        $afterId = trim((string) ($validated['after_id'] ?? ''));
        $query = $user->notifications()->reorder();

        if ($afterId !== '') {
            $lastSeen = $user->notifications()->whereKey($afterId)->first();

            if ($lastSeen) {
                $query->where('created_at', '>', $lastSeen->created_at);
            } else {
                // A UUID from another user or an unknown UUID must never replay history.
                $query->whereRaw('1 = 0');
            }
        } else {
            // The first request only establishes a baseline.
            $query->whereRaw('1 = 0');
        }

        $newNotifications = $query
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        $latest = $user->notifications()
            ->reorder()
            ->latest('created_at')
            ->first();

        return response()->json([
            'has_new' => $newNotifications->isNotEmpty(),
            'latest_id' => $latest?->id,
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $newNotifications
                ->map(fn (DatabaseNotification $notification): array => $this->formatNotification($notification))
                ->values(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'تم تعليم الإشعار كمقروء');
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'تم تعليم جميع الإشعارات كمقروءة');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $items = $request->user()
            ->unreadNotifications()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->formatNotification($notification));

        return response()->json(['items' => $items]);
    }

    public function destroy(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notification->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'تم حذف الإشعار');
    }

    public function destroyRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()
            ->notifications()
            ->whereNotNull('read_at')
            ->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'تم حذف جميع الإشعارات المقروءة');
    }

    private function formatNotification(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? 'إشعار جديد',
            'message' => $data['message'] ?? ($data['title'] ?? 'إشعار جديد'),
            'type' => $data['type'] ?? 'general',
            'url' => $data['url'] ?? null,
            'icon' => $data['icon'] ?? null,
            'time' => ArabicDate::compactDateTime(
                $notification->created_at
            ),
            'read_at' => $notification->read_at,
        ];
    }
}
