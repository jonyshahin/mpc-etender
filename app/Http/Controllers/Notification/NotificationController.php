<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => Notification::where('user_id', $user->id)->unread()->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back();
    }

    /**
     * API endpoint for the notification bell dropdown (returns JSON for partial reload).
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $lang = $user->language_pref ?? 'en';

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $lang === 'ar' ? ($n->title_ar ?: $n->title_en) : $n->title_en,
                'body' => $lang === 'ar' ? ($n->body_ar ?: $n->body_en) : $n->body_en,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
                'data' => $n->data,
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => Notification::where('user_id', $user->id)->unread()->count(),
        ]);
    }

    /**
     * Vendor notification list.
     *
     * The payload is named field by field. Paginating the models whole shipped
     * notifiable_type — a fully-qualified PHP class name — along with the
     * internal user_id/vendor_id addressing, none of which the page renders.
     */
    public function vendorIndex(Request $request): Response
    {
        $vendor = $request->user('vendor');

        $unreadOnly = $request->boolean('unread');

        $notifications = Notification::where('vendor_id', $vendor->id)
            ->when($unreadOnly, fn ($q) => $q->unread())
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Notification $n) => [
                'id' => $n->id,
                'notification_type' => $n->notification_type,
                'title_en' => $n->title_en,
                'title_ar' => $n->title_ar,
                'body_en' => $n->body_en,
                'body_ar' => $n->body_ar,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return Inertia::render('vendor/Notifications', [
            'notifications' => $notifications,
            'unreadCount' => Notification::where('vendor_id', $vendor->id)->unread()->count(),
            'filters' => ['unread' => $unreadOnly],
        ]);
    }

    public function vendorMarkRead(Request $request, Notification $notification): RedirectResponse
    {
        if ($notification->vendor_id !== $request->user('vendor')->id) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);

        return back();
    }

    /**
     * Clear the vendor's whole inbox.
     *
     * MPC users have had markAllRead since the feature landed; vendors reading
     * the same table through the same component did not, and had to open every
     * notification individually to clear the sidebar badge.
     */
    public function vendorMarkAllRead(Request $request): RedirectResponse
    {
        Notification::where('vendor_id', $request->user('vendor')->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back();
    }
}
