<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewVendorCategoryRequest;
use App\Models\VendorCategoryRequest;
use App\Models\VendorCategoryRequestEvidence;
use App\Services\FileUploadService;
use App\Services\VendorCategoryRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VendorCategoryRequestController extends Controller
{
    public function __construct(
        private VendorCategoryRequestService $service,
        private FileUploadService $files,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureCanReview($request);

        $status = $request->input('status', 'pending');

        $query = VendorCategoryRequest::query()
            ->with([
                'vendor:id,company_name,company_name_ar,email',
                'reviewer:id,name',
            ])
            ->withCount([
                'items as adds_count' => fn ($q) => $q->where('operation', 'add'),
                'items as removes_count' => fn ($q) => $q->where('operation', 'remove'),
                'evidence as evidence_count',
            ]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // MySQL-only FIELD() surfaces pending first in the "all" view.
        // SQLite (Pest) can't parse FIELD(); tests don't assert cross-status
        // ordering, so the conditional keeps both drivers happy.
        $query->when(
            DB::getDriverName() === 'mysql',
            fn ($q) => $q->orderByRaw("FIELD(status, 'pending', 'under_review', 'approved', 'rejected', 'withdrawn')"),
        )->orderByDesc('created_at');

        return Inertia::render('admin/VendorCategoryRequests/Index', [
            'requests' => $query->paginate(20)->withQueryString(),
            'filters' => ['status' => $status],
        ]);
    }

    public function show(Request $request, VendorCategoryRequest $categoryRequest): Response
    {
        $this->ensureCanReview($request);

        $categoryRequest->load([
            'items.category:id,name_en,name_ar,parent_id',
            'evidence:id,request_id,original_name,mime_type,size,path,created_at',
            'vendor:id,company_name,company_name_ar,email',
            'reviewer:id,name',
        ]);

        $mapItem = fn ($i) => [
            'category_id' => $i->category_id,
            'name_en' => $i->category?->name_en,
            'name_ar' => $i->category?->name_ar,
            'parent_id' => $i->category?->parent_id,
        ];

        return Inertia::render('admin/VendorCategoryRequests/Show', [
            'request' => [
                'id' => $categoryRequest->id,
                'status' => $categoryRequest->status,
                'justification' => $categoryRequest->justification,
                'reviewer_comments' => $categoryRequest->reviewer_comments,
                'withdraw_reason' => $categoryRequest->withdraw_reason,
                'vendor' => [
                    'id' => $categoryRequest->vendor->id,
                    'company_name' => $categoryRequest->vendor->company_name,
                    'company_name_ar' => $categoryRequest->vendor->company_name_ar,
                    'email' => $categoryRequest->vendor->email,
                ],
                'reviewer' => $categoryRequest->reviewer
                    ? ['id' => $categoryRequest->reviewer->id, 'name' => $categoryRequest->reviewer->name]
                    : null,
                'reviewed_at' => $categoryRequest->reviewed_at?->toIso8601String(),
                'created_at' => $categoryRequest->created_at->toIso8601String(),
                'updated_at' => $categoryRequest->updated_at->toIso8601String(),
                'adds' => $categoryRequest->items->where('operation', 'add')->map($mapItem)->values(),
                'removes' => $categoryRequest->items->where('operation', 'remove')->map($mapItem)->values(),
                'evidence' => $categoryRequest->evidence->map(fn ($e) => [
                    'id' => $e->id,
                    'original_name' => $e->original_name,
                    'mime_type' => $e->mime_type,
                    'size' => $e->size,
                    'created_at' => $e->created_at->toIso8601String(),
                    'download_url' => route('admin.vendor-category-requests.evidence.download', $e->id),
                ])->values(),
            ],
        ]);
    }

    public function approve(ReviewVendorCategoryRequest $request, VendorCategoryRequest $categoryRequest): RedirectResponse
    {
        // FormRequest::authorize() already enforced vendors.review_category_requests.
        // Service catches terminal-state approvals and throws ValidationException.
        abort_if($request->input('action') !== 'approve', 422);

        $this->service->approve(
            $categoryRequest,
            $request->user('web'),
            $request->input('comments'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_category_request_approved'),
        ]);

        return redirect()->route('admin.vendor-category-requests.show', $categoryRequest);
    }

    public function reject(ReviewVendorCategoryRequest $request, VendorCategoryRequest $categoryRequest): RedirectResponse
    {
        abort_if($request->input('action') !== 'reject', 422);

        $this->service->reject(
            $categoryRequest,
            $request->user('web'),
            (string) $request->input('comments'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_category_request_rejected'),
        ]);

        return redirect()->route('admin.vendor-category-requests.show', $categoryRequest);
    }

    public function downloadEvidence(Request $request, VendorCategoryRequestEvidence $evidence): RedirectResponse
    {
        $this->ensureCanReview($request);

        return redirect()->away($this->files->getTemporaryUrl($evidence->path, 15));
    }

    /**
     * GET endpoints don't route through ReviewVendorCategoryRequest, so guard
     * them here with the same permission check.
     */
    private function ensureCanReview(Request $request): void
    {
        $user = $request->user('web');
        abort_unless($user && $user->hasPermission('vendors.review_category_requests'), 403);
    }
}
