<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\DocumentAccessLog;
use App\Models\Tender;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenderBrowseController extends Controller
{
    /**
     * Show published tenders matching vendor's categories.
     */
    public function index(Request $request): Response
    {
        $vendor = $request->user('vendor');

        $vendorCategoryIds = $vendor->categories()->pluck('categories.id');

        $tenders = Tender::query()
            ->published()
            ->whereHas('categories', function ($query) use ($vendorCategoryIds) {
                $query->whereIn('categories.id', $vendorCategoryIds);
            })
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%");
                });
            })
            ->with(['project:id,name', 'categories:id,name'])
            ->withCount('bids')
            ->latest('published_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('vendor/Tenders/Browse', [
            'tenders' => $tenders,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show tender detail from vendor perspective.
     * Loads BOQ (read-only), documents, addenda, and published clarifications.
     */
    public function show(Request $request, Tender $tender): Response
    {
        $vendor = $request->user('vendor');

        $tender->load([
            'project:id,name,description',
            'categories:id,name',
            'boqSections.items',
            'documents',
            'addenda' => fn ($q) => $q->latest(),
            'clarifications' => fn ($q) => $q->where('is_published', true)->latest(),
        ]);

        // Log document access
        DocumentAccessLog::create([
            'user_id' => $vendor->id,
            'user_type' => get_class($vendor),
            'document_type' => Tender::class,
            'document_id' => $tender->id,
            'action' => 'viewed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return Inertia::render('vendor/Tenders/Show', [
            'tender' => $tender,
            'hasExistingBid' => $tender->bids()
                ->where('vendor_id', $vendor->id)
                ->whereNot('status', 'withdrawn')
                ->exists(),
        ]);
    }
}
