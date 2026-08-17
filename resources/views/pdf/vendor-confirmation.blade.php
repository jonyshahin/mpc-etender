{{--
    PDF twin of resources/js/pages/admin/Vendors/Confirmation.tsx.

    Kept as a separate Blade view because dompdf renders HTML without executing
    JavaScript, so the React page cannot be reused. dompdf also performs no
    Arabic shaping or bidi reordering, which is why the Arabic company name is
    omitted here — the browser Print button produces an Arabic-faithful copy.

    Images are referenced by absolute filesystem path: dompdf cannot fetch over
    HTTP, and in tests there is no server to fetch from.
--}}
@php
    // Prepared by PrintAssetService: an absolute path for vectors, a downscaled
    // data URI for rasters, or null when the file is missing.
    $logoPath = $logoSrc ?? null;
    $reference = strtoupper(explode('-', $vendor->id)[0]);
    $location = collect([$vendor->city, $vendor->country])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vendor Application Confirmation — {{ $vendor->company_name }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        .header { border-bottom: 2px solid #C09B72; padding-bottom: 12px; }
        .header td { vertical-align: top; }
        .logo { width: 92px; }
        .project { font-size: 17px; font-weight: bold; color: #111827; }
        .company { font-size: 12px; color: #4b5563; margin-top: 2px; }
        .doctitle { font-size: 11px; color: #6b7280; margin-top: 6px; text-transform: uppercase; letter-spacing: 1px; }
        .company-mark { text-align: right; vertical-align: middle; }
        .meta-row { margin-top: 8px; font-size: 10px; color: #4b5563; }
        .meta-row .label { text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; }
        .meta-row .value { font-family: DejaVu Sans Mono, monospace; font-weight: bold; color: #111827; }
        .text-right { text-align: right; }
        h2 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280;
             margin: 22px 0 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        table.fields { width: 100%; border-collapse: collapse; }
        table.fields td { padding: 4px 0; vertical-align: top; width: 50%; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
        .value { font-size: 11px; font-weight: bold; }
        .chip { display: inline-block; border: 1px solid #d1d5db; border-radius: 3px;
                padding: 2px 7px; margin: 0 4px 4px 0; font-size: 10px; }
        .credentials { border: 1px solid #C09B72; background: #faf6f1; padding: 10px 12px; margin-top: 18px; }
        .credentials .heading { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #7c5c38; }
        .credentials .password { font-family: DejaVu Sans Mono, monospace; font-size: 15px;
                                 font-weight: bold; letter-spacing: 1px; margin-top: 4px; }
        .credentials .note { font-size: 9px; color: #6b7280; margin-top: 5px; }
        .footer { margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
        .footer td { vertical-align: bottom; }
        .note { font-size: 9px; color: #6b7280; line-height: 1.5; }
        .qr { text-align: center; }
        .qr img { width: 104px; height: 104px; }
        .qr .caption { font-size: 8px; color: #6b7280; margin-top: 3px; }
    </style>
</head>
<body>
    {{-- Two marks: the project (Boulevard) on the left, the construction company
         (MPC) on the right. The reference row sits below the rule so neither is
         crowded. --}}
    <table class="header" width="100%">
        <tr>
            <td width="62%">
                <table>
                    <tr>
                        @if ($logoPath)
                            <td class="logo"><img src="{{ $logoPath }}" width="80"></td>
                        @endif
                        <td style="padding-left: 12px;">
                            @if ($projectName)
                                <div class="project">{{ $projectName }}</div>
                            @endif
                            <div class="doctitle">Vendor Application Confirmation</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="company-mark">
                <table align="right">
                    <tr>
                        <td class="company" style="padding-right: 10px;">{{ $companyName }}</td>
                        @if ($companyLogoSrc)
                            <td><img src="{{ $companyLogoSrc }}" width="58"></td>
                        @endif
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="meta-row" width="100%">
        <tr>
            <td><span class="label">Reference</span> <span class="value">{{ $reference }}</span></td>
            <td class="text-right">
                <span class="label">Issued on</span>
                <span>{{ \Illuminate\Support\Carbon::parse($generatedAt)->format('j F Y') }}</span>
            </td>
        </tr>
    </table>

    <h2>Company Information</h2>
    <table class="fields">
        <tr>
            <td><div class="label">Company Name</div><div class="value">{{ $vendor->company_name }}</div></td>
            <td><div class="label">Trade License No.</div><div class="value">{{ $vendor->trade_license_no ?: '—' }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Address</div><div class="value">{{ $vendor->address ?: '—' }}</div></td>
            <td><div class="label">City</div><div class="value">{{ $location ?: '—' }}</div></td>
        </tr>
        <tr>
            <td colspan="2"><div class="label">Website</div><div class="value">{{ $vendor->website ?: '—' }}</div></td>
        </tr>
    </table>

    <h2>Contact Person</h2>
    <table class="fields">
        <tr>
            <td><div class="label">Contact Person</div><div class="value">{{ $vendor->contact_person }}</div></td>
            <td><div class="label">Email</div><div class="value">{{ $vendor->email }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Phone</div><div class="value">{{ $vendor->phone ?: '—' }}</div></td>
            <td><div class="label">WhatsApp</div><div class="value">{{ $vendor->whatsapp_number ?: '—' }}</div></td>
        </tr>
    </table>

    <h2>Business Categories</h2>
    <div>
        @forelse ($vendor->categories as $category)
            <span class="chip">{{ $category->name_en }}</span>
        @empty
            <span class="value">—</span>
        @endforelse
    </div>

    @if ($temporaryPassword)
        <div class="credentials">
            <div class="heading">Portal sign-in — {{ $vendor->email }}</div>
            <div class="password">{{ $temporaryPassword }}</div>
            <div class="note">
                Temporary password. The vendor is required to change it on first sign-in.
                Hand this letter over directly and do not send the password by email.
            </div>
        </div>
    @endif

    <table class="footer" width="100%">
        <tr>
            <td width="72%">
                <div class="label">Application status</div>
                <div class="value" style="margin-bottom: 8px;">{{ ucfirst(str_replace('_', ' ', $vendor->prequalification_status->value ?? $vendor->prequalification_status)) }}</div>
                <div class="note">
                    This letter confirms that the details above are recorded in the
                    {{ $companyName }} e-Tender system. Prequalification is assessed
                    separately &mdash; the status shown reflects the application at the
                    time of issue.
                </div>
            </td>
            <td class="qr">
                <img src="{{ $qrCode }}">
                <div class="caption">{{ $websiteUrl }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
