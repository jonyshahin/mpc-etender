<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VendorPrequalificationRequest;
use App\Models\Vendor;
use App\Services\FileUploadService;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function __construct(
        private VendorService $vendorService,
        private FileUploadService $fileUploadService,
    ) {}

    public function index(Request $request): Response
    {
        $query = Vendor::with('categories:id,name_en')
            ->select('id', 'company_name', 'email', 'prequalification_status', 'qualified_at', 'city', 'country', 'created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('prequalification_status', $status);
        }

        if ($categoryId = $request->input('category_id')) {
            $query->inCategory($categoryId);
        }

        $query->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'));

        return Inertia::render('admin/Vendors/Index', [
            'vendors' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only('search', 'status', 'category_id', 'sort', 'direction'),
        ]);
    }

    public function show(Vendor $vendor): Response
    {
        $vendor->load([
            'documents' => fn ($q) => $q->orderByDesc('created_at'),
            'categories:id,name_en,name_ar',
            'qualifiedBy:id,name',
        ]);

        $documentUrls = [];
        foreach ($vendor->documents as $doc) {
            $documentUrls[$doc->id] = $this->fileUploadService->getTemporaryUrl($doc->file_path);
        }

        return Inertia::render('admin/Vendors/Show', [
            'vendor' => $vendor,
            'documentUrls' => $documentUrls,
        ]);
    }

    public function prequalify(VendorPrequalificationRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->vendorService->prequalify($vendor, $request->user());

        return back()->with('flash', ['type' => 'success', 'message' => __('Vendor approved successfully.')]);
    }

    public function reject(VendorPrequalificationRequest $request, Vendor $vendor): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->vendorService->reject($vendor, $request->user(), $request->input('reason'));

        return back()->with('flash', ['type' => 'success', 'message' => __('Vendor rejected.')]);
    }

    public function suspend(VendorPrequalificationRequest $request, Vendor $vendor): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->vendorService->suspend($vendor, $request->user(), $request->input('reason'));

        return back()->with('flash', ['type' => 'success', 'message' => __('Vendor suspended.')]);
    }
}
