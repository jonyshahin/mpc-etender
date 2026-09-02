<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\BidStatus;
use App\Enums\VendorDocStatus;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tender;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** How far ahead a document expiry counts as "coming up". */
    private const EXPIRY_WARNING_DAYS = 30;

    public function index(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->user('vendor');

        $categoryIds = $vendor->categories()->active()->pluck('categories.id');

        $expiring = $this->onFile($vendor)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(self::EXPIRY_WARNING_DAYS)])
            ->orderBy('expiry_date')
            ->get(['id', 'title', 'document_type', 'expiry_date']);

        $expired = $this->onFile($vendor)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->orderByDesc('expiry_date')
            ->get(['id', 'title', 'document_type', 'expiry_date']);

        $rejectedDocuments = $vendor->documents()
            ->where('status', VendorDocStatus::Rejected)
            ->count();

        // openForBids() rather than another where('status', 'published'): that
        // literal was the only copy of the value living outside
        // App\Enums\TenderStatus, and the scope carries the deadline half of
        // the definition alongside it.
        $openTenders = fn () => Tender::query()
            ->openForBids()
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->select('id', 'title_en', 'title_ar', 'reference_number', 'submission_deadline')
            ->orderBy('submission_deadline');

        return Inertia::render('vendor/Dashboard', [
            'vendor' => [
                'company_name' => $vendor->company_name,
                'company_name_ar' => $vendor->company_name_ar,
                'prequalification_status' => $vendor->prequalification_status,
                'qualified_at' => $vendor->qualified_at?->toIso8601String(),
            ],
            'summary' => [
                'open_tenders' => $openTenders()->count(),
                'active_bids' => $vendor->bids()
                    ->whereIn('status', [BidStatus::Draft, BidStatus::Submitted, BidStatus::UnderEvaluation])
                    ->count(),
                // One figure for "your paperwork needs you", so the tile is
                // actionable rather than merely informative.
                'documents_needing_attention' => $expiring->count() + $expired->count() + $rejectedDocuments,
                'categories' => $categoryIds->count(),
                'unread_notifications' => Notification::where('vendor_id', $vendor->id)->unread()->count(),
            ],
            'documentBreakdown' => $this->documentBreakdown($vendor),
            'documentWarnings' => $expiring,
            'expiredDocuments' => $expired,
            'openTenders' => $openTenders()->take(6)->get(),
            'recentBids' => $vendor->bids()
                ->with('tender:id,title_en,title_ar,reference_number')
                ->select('id', 'tender_id', 'status', 'submitted_at')
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }

    /**
     * Documents that count toward the vendor's standing.
     *
     * Rejected documents are excluded from every expiry calculation on this
     * page. Filtering on expiry_date alone reported a document MPC had already
     * turned down as an expired credential, which invites the one remedy that
     * cannot help: renewing and re-uploading a document that was never
     * accepted. Rejections are surfaced as rejections instead.
     */
    private function onFile(Vendor $vendor): HasMany
    {
        return $vendor->documents()
            ->whereIn('status', [VendorDocStatus::Pending, VendorDocStatus::Approved]);
    }

    /**
     * Document counts per review state, every state present.
     *
     * @return array<int, array{status: string, count: int}>
     */
    private function documentBreakdown(Vendor $vendor): array
    {
        $counts = $vendor->documents()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return array_map(
            fn (VendorDocStatus $case) => [
                'status' => $case->value,
                'count' => (int) ($counts[$case->value] ?? 0),
            ],
            // Expired is a review state nothing ever sets — expiry is derived
            // from expiry_date — so it would only render a permanently empty
            // row.
            array_values(array_filter(
                VendorDocStatus::cases(),
                fn (VendorDocStatus $case) => $case !== VendorDocStatus::Expired,
            )),
        );
    }
}
