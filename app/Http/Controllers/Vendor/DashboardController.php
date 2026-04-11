<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $vendor = $request->user('vendor');

        $categoryIds = $vendor->categories()->pluck('categories.id');

        return Inertia::render('vendor/Dashboard', [
            'vendor' => $vendor->only('id', 'company_name', 'prequalification_status', 'qualified_at'),
            'documentWarnings' => $vendor->documents()
                ->where('expiry_date', '<=', now()->addDays(30))
                ->where('expiry_date', '>', now())
                ->select('id', 'title', 'expiry_date')
                ->get(),
            'expiredDocuments' => $vendor->documents()
                ->where('expiry_date', '<=', now())
                ->select('id', 'title', 'expiry_date')
                ->get(),
            'openTenders' => Tender::where('status', 'published')
                ->where('submission_deadline', '>', now())
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
                ->select('id', 'title_en', 'title_ar', 'reference_number', 'submission_deadline')
                ->orderBy('submission_deadline')
                ->take(10)
                ->get(),
            'submittedBids' => $vendor->bids()
                ->with('tender:id,title_en,title_ar,reference_number')
                ->select('id', 'tender_id', 'status', 'submitted_at')
                ->latest()
                ->take(10)
                ->get(),
        ]);
    }
}
