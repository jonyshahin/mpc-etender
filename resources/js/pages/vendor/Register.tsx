import { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { ChevronRight, ChevronLeft, ChevronDown, ChevronUp, Check } from 'lucide-react';

type Category = {
    id: string;
    name_en: string;
    name_ar: string | null;
    parent_id: string | null;
    children?: Category[];
};

type Props = {
    categories: Category[];
};

const STEPS = [
    { number: 1, title: 'Company Info' },
    { number: 2, title: 'Contact Person' },
    { number: 3, title: 'Categories' },
    { number: 4, title: 'Review' },
    { number: 5, title: 'Submit' },
];

function StepIndicator({ currentStep }: { currentStep: number }) {
    return (
        <div className="flex items-center justify-center gap-2 mb-8">
            {STEPS.map((step, index) => (
                <div key={step.number} className="flex items-center">
                    <div className="flex flex-col items-center">
                        <div
                            className={`flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-semibold transition-colors ${
                                currentStep === step.number
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : currentStep > step.number
                                      ? 'border-primary bg-primary/10 text-primary'
                                      : 'border-muted-foreground/30 text-muted-foreground'
                            }`}
                        >
                            {currentStep > step.number ? <Check className="h-5 w-5" /> : step.number}
                        </div>
                        <span className="mt-1 text-xs text-muted-foreground">{step.title}</span>
                    </div>
                    {index < STEPS.length - 1 && (
                        <div
                            className={`mx-2 h-0.5 w-12 ${
                                currentStep > step.number ? 'bg-primary' : 'bg-muted-foreground/30'
                            }`}
                        />
                    )}
                </div>
            ))}
        </div>
    );
}

function CategoryTree({
    categories,
    selectedIds,
    onToggle,
}: {
    categories: Category[];
    selectedIds: string[];
    onToggle: (id: string) => void;
}) {
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const toggleExpand = (id: string) => {
        setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    return (
        <div className="space-y-2">
            {categories.map((category) => (
                <div key={category.id} className="border rounded-lg p-3">
                    <div className="flex items-center gap-2">
                        {category.children && category.children.length > 0 && (
                            <button type="button" onClick={() => toggleExpand(category.id)} className="p-1">
                                {expanded[category.id] ? (
                                    <ChevronDown className="h-4 w-4" />
                                ) : (
                                    <ChevronRight className="h-4 w-4" />
                                )}
                            </button>
                        )}
                        <Checkbox
                            id={`cat-${category.id}`}
                            checked={selectedIds.includes(category.id)}
                            onCheckedChange={() => onToggle(category.id)}
                        />
                        <Label htmlFor={`cat-${category.id}`} className="cursor-pointer font-medium">
                            {category.name_en}
                            {category.name_ar && <span className="text-muted-foreground ms-2">({category.name_ar})</span>}
                        </Label>
                    </div>
                    {expanded[category.id] && category.children && category.children.length > 0 && (
                        <div className="ms-8 mt-2 space-y-2">
                            {category.children.map((child) => (
                                <div key={child.id} className="flex items-center gap-2">
                                    <Checkbox
                                        id={`cat-${child.id}`}
                                        checked={selectedIds.includes(child.id)}
                                        onCheckedChange={() => onToggle(child.id)}
                                    />
                                    <Label htmlFor={`cat-${child.id}`} className="cursor-pointer">
                                        {child.name_en}
                                        {child.name_ar && (
                                            <span className="text-muted-foreground ms-2">({child.name_ar})</span>
                                        )}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

export default function Register({ categories }: Props) {
    const [currentStep, setCurrentStep] = useState(1);

    const form = useForm({
        company_name: '',
        company_name_ar: '',
        trade_license_no: '',
        address: '',
        city: '',
        country: '',
        website: '',
        contact_person: '',
        email: '',
        password: '',
        password_confirmation: '',
        phone: '',
        whatsapp_number: '',
        category_ids: [] as string[],
    });

    const validateStep = (step: number): boolean => {
        switch (step) {
            case 1:
                return !!(form.data.company_name && form.data.trade_license_no && form.data.address && form.data.city && form.data.country);
            case 2:
                return !!(
                    form.data.contact_person &&
                    form.data.email &&
                    form.data.password &&
                    form.data.password_confirmation &&
                    form.data.phone &&
                    form.data.password === form.data.password_confirmation
                );
            case 3:
                return form.data.category_ids.length > 0;
            default:
                return true;
        }
    };

    const handleNext = () => {
        if (validateStep(currentStep)) {
            setCurrentStep((prev) => Math.min(prev + 1, 5));
        }
    };

    const handlePrevious = () => {
        setCurrentStep((prev) => Math.max(prev - 1, 1));
    };

    const handleCategoryToggle = (id: string) => {
        const current = form.data.category_ids;
        if (current.includes(id)) {
            form.setData('category_ids', current.filter((cid) => cid !== id));
        } else {
            form.setData('category_ids', [...current, id]);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/vendor/register');
    };

    return (
        <>
            <Head title="Vendor Registration" />

            <div className="flex min-h-screen items-center justify-center bg-background p-4">
                <div className="w-full max-w-2xl">
                    <div className="mb-6 text-center">
                        <h1 className="text-2xl font-bold">Vendor Registration</h1>
                        <p className="text-muted-foreground">Register your company on the MPC e-Tender platform</p>
                    </div>

                    <StepIndicator currentStep={currentStep} />

                    <Card>
                        <CardContent className="pt-6">
                            <form onSubmit={handleSubmit}>
                                {/* Step 1: Company Info */}
                                {currentStep === 1 && (
                                    <div className="space-y-4">
                                        <CardHeader className="p-0 pb-4">
                                            <CardTitle>Company Information</CardTitle>
                                            <CardDescription>Enter your company details</CardDescription>
                                        </CardHeader>
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="company_name">Company Name *</Label>
                                                <Input
                                                    id="company_name"
                                                    value={form.data.company_name}
                                                    onChange={(e) => form.setData('company_name', e.target.value)}
                                                />
                                                {form.errors.company_name && (
                                                    <p className="text-sm text-destructive">{form.errors.company_name}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="company_name_ar">Company Name (Arabic)</Label>
                                                <Input
                                                    id="company_name_ar"
                                                    dir="rtl"
                                                    value={form.data.company_name_ar}
                                                    onChange={(e) => form.setData('company_name_ar', e.target.value)}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="trade_license_no">Trade License No *</Label>
                                                <Input
                                                    id="trade_license_no"
                                                    value={form.data.trade_license_no}
                                                    onChange={(e) => form.setData('trade_license_no', e.target.value)}
                                                />
                                                {form.errors.trade_license_no && (
                                                    <p className="text-sm text-destructive">{form.errors.trade_license_no}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="website">Website</Label>
                                                <Input
                                                    id="website"
                                                    type="url"
                                                    value={form.data.website}
                                                    onChange={(e) => form.setData('website', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="address">Address *</Label>
                                            <Input
                                                id="address"
                                                value={form.data.address}
                                                onChange={(e) => form.setData('address', e.target.value)}
                                            />
                                            {form.errors.address && (
                                                <p className="text-sm text-destructive">{form.errors.address}</p>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="city">City *</Label>
                                                <Input
                                                    id="city"
                                                    value={form.data.city}
                                                    onChange={(e) => form.setData('city', e.target.value)}
                                                />
                                                {form.errors.city && (
                                                    <p className="text-sm text-destructive">{form.errors.city}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="country">Country *</Label>
                                                <Input
                                                    id="country"
                                                    value={form.data.country}
                                                    onChange={(e) => form.setData('country', e.target.value)}
                                                />
                                                {form.errors.country && (
                                                    <p className="text-sm text-destructive">{form.errors.country}</p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Step 2: Contact Person */}
                                {currentStep === 2 && (
                                    <div className="space-y-4">
                                        <CardHeader className="p-0 pb-4">
                                            <CardTitle>Contact Person</CardTitle>
                                            <CardDescription>Provide contact and login details</CardDescription>
                                        </CardHeader>
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="contact_person">Contact Person *</Label>
                                                <Input
                                                    id="contact_person"
                                                    value={form.data.contact_person}
                                                    onChange={(e) => form.setData('contact_person', e.target.value)}
                                                />
                                                {form.errors.contact_person && (
                                                    <p className="text-sm text-destructive">{form.errors.contact_person}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="email">Email *</Label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    value={form.data.email}
                                                    onChange={(e) => form.setData('email', e.target.value)}
                                                />
                                                {form.errors.email && (
                                                    <p className="text-sm text-destructive">{form.errors.email}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="password">Password *</Label>
                                                <Input
                                                    id="password"
                                                    type="password"
                                                    value={form.data.password}
                                                    onChange={(e) => form.setData('password', e.target.value)}
                                                />
                                                {form.errors.password && (
                                                    <p className="text-sm text-destructive">{form.errors.password}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="password_confirmation">Confirm Password *</Label>
                                                <Input
                                                    id="password_confirmation"
                                                    type="password"
                                                    value={form.data.password_confirmation}
                                                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                                />
                                                {form.data.password &&
                                                    form.data.password_confirmation &&
                                                    form.data.password !== form.data.password_confirmation && (
                                                        <p className="text-sm text-destructive">Passwords do not match</p>
                                                    )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="phone">Phone *</Label>
                                                <Input
                                                    id="phone"
                                                    type="tel"
                                                    value={form.data.phone}
                                                    onChange={(e) => form.setData('phone', e.target.value)}
                                                />
                                                {form.errors.phone && (
                                                    <p className="text-sm text-destructive">{form.errors.phone}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="whatsapp_number">WhatsApp Number</Label>
                                                <Input
                                                    id="whatsapp_number"
                                                    type="tel"
                                                    value={form.data.whatsapp_number}
                                                    onChange={(e) => form.setData('whatsapp_number', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Step 3: Categories */}
                                {currentStep === 3 && (
                                    <div className="space-y-4">
                                        <CardHeader className="p-0 pb-4">
                                            <CardTitle>Business Categories</CardTitle>
                                            <CardDescription>Select the categories your company operates in</CardDescription>
                                        </CardHeader>
                                        <CategoryTree
                                            categories={categories}
                                            selectedIds={form.data.category_ids}
                                            onToggle={handleCategoryToggle}
                                        />
                                        {form.data.category_ids.length === 0 && (
                                            <p className="text-sm text-muted-foreground">Please select at least one category</p>
                                        )}
                                    </div>
                                )}

                                {/* Step 4: Review */}
                                {currentStep === 4 && (
                                    <div className="space-y-4">
                                        <CardHeader className="p-0 pb-4">
                                            <CardTitle>Review Your Information</CardTitle>
                                            <CardDescription>Please verify all details before submitting</CardDescription>
                                        </CardHeader>

                                        <div className="space-y-4">
                                            <div className="rounded-lg border p-4">
                                                <h3 className="mb-2 font-semibold">Company Information</h3>
                                                <dl className="grid grid-cols-2 gap-2 text-sm">
                                                    <dt className="text-muted-foreground">Company Name</dt>
                                                    <dd>{form.data.company_name}</dd>
                                                    {form.data.company_name_ar && (
                                                        <>
                                                            <dt className="text-muted-foreground">Company Name (AR)</dt>
                                                            <dd>{form.data.company_name_ar}</dd>
                                                        </>
                                                    )}
                                                    <dt className="text-muted-foreground">Trade License</dt>
                                                    <dd>{form.data.trade_license_no}</dd>
                                                    <dt className="text-muted-foreground">Address</dt>
                                                    <dd>{form.data.address}</dd>
                                                    <dt className="text-muted-foreground">City</dt>
                                                    <dd>{form.data.city}</dd>
                                                    <dt className="text-muted-foreground">Country</dt>
                                                    <dd>{form.data.country}</dd>
                                                    {form.data.website && (
                                                        <>
                                                            <dt className="text-muted-foreground">Website</dt>
                                                            <dd>{form.data.website}</dd>
                                                        </>
                                                    )}
                                                </dl>
                                            </div>

                                            <div className="rounded-lg border p-4">
                                                <h3 className="mb-2 font-semibold">Contact Person</h3>
                                                <dl className="grid grid-cols-2 gap-2 text-sm">
                                                    <dt className="text-muted-foreground">Name</dt>
                                                    <dd>{form.data.contact_person}</dd>
                                                    <dt className="text-muted-foreground">Email</dt>
                                                    <dd>{form.data.email}</dd>
                                                    <dt className="text-muted-foreground">Phone</dt>
                                                    <dd>{form.data.phone}</dd>
                                                    {form.data.whatsapp_number && (
                                                        <>
                                                            <dt className="text-muted-foreground">WhatsApp</dt>
                                                            <dd>{form.data.whatsapp_number}</dd>
                                                        </>
                                                    )}
                                                </dl>
                                            </div>

                                            <div className="rounded-lg border p-4">
                                                <h3 className="mb-2 font-semibold">Selected Categories</h3>
                                                <p className="text-sm">
                                                    {form.data.category_ids.length} categories selected
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Step 5: Submit */}
                                {currentStep === 5 && (
                                    <div className="space-y-4 text-center py-8">
                                        <CardHeader className="p-0 pb-4">
                                            <CardTitle>Ready to Submit</CardTitle>
                                            <CardDescription>
                                                Your registration will be reviewed by the MPC team. You will receive an email
                                                notification once your account has been approved.
                                            </CardDescription>
                                        </CardHeader>

                                        {form.errors && Object.keys(form.errors).length > 0 && (
                                            <div className="rounded-lg border border-destructive bg-destructive/10 p-4 text-sm text-destructive">
                                                <p className="font-semibold">Please fix the following errors:</p>
                                                <ul className="mt-2 list-disc list-inside">
                                                    {Object.entries(form.errors).map(([key, message]) => (
                                                        <li key={key}>{message}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Navigation */}
                                <div className="mt-6 flex items-center justify-between">
                                    <div>
                                        {currentStep > 1 && (
                                            <Button type="button" variant="outline" onClick={handlePrevious}>
                                                <ChevronLeft className="mr-1 h-4 w-4" />
                                                Previous
                                            </Button>
                                        )}
                                    </div>
                                    <div>
                                        {currentStep < 5 ? (
                                            <Button
                                                type="button"
                                                onClick={handleNext}
                                                disabled={!validateStep(currentStep)}
                                            >
                                                Next
                                                <ChevronRight className="ml-1 h-4 w-4" />
                                            </Button>
                                        ) : (
                                            <Button type="submit" disabled={form.processing}>
                                                {form.processing ? 'Submitting...' : 'Submit Registration'}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <p className="mt-4 text-center text-sm text-muted-foreground">
                        Already have an account?{' '}
                        <Link href="/vendor/login" className="text-primary underline">
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </>
    );
}
