import { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { ChevronRight, ChevronLeft, ChevronDown, ChevronUp, Check } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

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

const STEP_KEYS = [
    { number: 1, key: 'auth.step_company_info' },
    { number: 2, key: 'auth.step_contact_person' },
    { number: 3, key: 'auth.step_categories' },
    { number: 4, key: 'auth.step_review' },
    { number: 5, key: 'auth.step_submit' },
];

function StepIndicator({ currentStep }: { currentStep: number }) {
    const { t } = useTranslation();
    const STEPS = STEP_KEYS.map((s) => ({ number: s.number, title: t(s.key) }));
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
    const { t } = useTranslation();
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
            <Head title={t('auth.vendor_registration')} />

            <div className="flex min-h-screen items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-2xl">
                    <div className="mb-8 flex flex-col items-center gap-6">
                        <Link href="/" className="font-medium">
                            <AppLogoIcon className="size-24 object-contain md:size-28" />
                            <span className="sr-only">MPC Group</span>
                        </Link>
                        <div className="space-y-2 text-center">
                            <h1 className="text-2xl font-bold">{t('auth.vendor_registration')}</h1>
                            <p className="text-muted-foreground">{t('auth.registration_description')}</p>
                        </div>
                    </div>

                    <StepIndicator currentStep={currentStep} />

                    <Card>
                        <CardContent className="pt-6">
                            <form onSubmit={handleSubmit}>
                                {/* Step 1: Company Info */}
                                {currentStep === 1 && (
                                    <div className="space-y-4">
                                        <CardHeader className="p-0 pb-4">
                                            <CardTitle>{t('vendor.company_information')}</CardTitle>
                                            <CardDescription>{t('auth.enter_company_details')}</CardDescription>
                                        </CardHeader>
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="company_name">{t('form.company_name')} *</Label>
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
                                                <Label htmlFor="company_name_ar">{t('form.company_name_ar')}</Label>
                                                <Input
                                                    id="company_name_ar"
                                                    dir="rtl"
                                                    value={form.data.company_name_ar}
                                                    onChange={(e) => form.setData('company_name_ar', e.target.value)}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="trade_license_no">{t('form.trade_license_no')} *</Label>
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
                                                <Label htmlFor="website">{t('form.website')}</Label>
                                                <Input
                                                    id="website"
                                                    type="url"
                                                    value={form.data.website}
                                                    onChange={(e) => form.setData('website', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="address">{t('form.address')} *</Label>
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
                                                <Label htmlFor="city">{t('form.city')} *</Label>
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
                                                <Label htmlFor="country">{t('form.country')} *</Label>
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
                                            <CardTitle>{t('vendor.contact_person')}</CardTitle>
                                            <CardDescription>{t('auth.provide_contact_details')}</CardDescription>
                                        </CardHeader>
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="contact_person">{t('form.contact_person')} *</Label>
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
                                                <Label htmlFor="email">{t('form.email')} *</Label>
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
                                                <Label htmlFor="password">{t('form.password')} *</Label>
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
                                                <Label htmlFor="password_confirmation">{t('form.confirm_password')} *</Label>
                                                <Input
                                                    id="password_confirmation"
                                                    type="password"
                                                    value={form.data.password_confirmation}
                                                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                                />
                                                {form.data.password &&
                                                    form.data.password_confirmation &&
                                                    form.data.password !== form.data.password_confirmation && (
                                                        <p className="text-sm text-destructive">{t('auth.passwords_do_not_match')}</p>
                                                    )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="phone">{t('form.phone')} *</Label>
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
                                                <Label htmlFor="whatsapp_number">{t('form.whatsapp_number')}</Label>
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
                                            <CardTitle>{t('pages.vendor.business_categories')}</CardTitle>
                                            <CardDescription>{t('vendor.select_categories_description')}</CardDescription>
                                        </CardHeader>
                                        <CategoryTree
                                            categories={categories}
                                            selectedIds={form.data.category_ids}
                                            onToggle={handleCategoryToggle}
                                        />
                                        {form.data.category_ids.length === 0 && (
                                            <p className="text-sm text-muted-foreground">{t('auth.select_at_least_one_category')}</p>
                                        )}
                                    </div>
                                )}

                                {/* Step 4: Review */}
                                {currentStep === 4 && (
                                    <div className="space-y-4">
                                        <CardHeader className="p-0 pb-4">
                                            <CardTitle>{t('auth.review_your_information')}</CardTitle>
                                            <CardDescription>{t('auth.verify_details_before_submitting')}</CardDescription>
                                        </CardHeader>

                                        <div className="space-y-4">
                                            <div className="rounded-lg border p-4">
                                                <h3 className="mb-2 font-semibold">{t('vendor.company_information')}</h3>
                                                <dl className="grid grid-cols-2 gap-2 text-sm">
                                                    <dt className="text-muted-foreground">{t('form.company_name')}</dt>
                                                    <dd>{form.data.company_name}</dd>
                                                    {form.data.company_name_ar && (
                                                        <>
                                                            <dt className="text-muted-foreground">{t('form.company_name_ar')}</dt>
                                                            <dd>{form.data.company_name_ar}</dd>
                                                        </>
                                                    )}
                                                    <dt className="text-muted-foreground">{t('form.trade_license_no')}</dt>
                                                    <dd>{form.data.trade_license_no}</dd>
                                                    <dt className="text-muted-foreground">{t('form.address')}</dt>
                                                    <dd>{form.data.address}</dd>
                                                    <dt className="text-muted-foreground">{t('form.city')}</dt>
                                                    <dd>{form.data.city}</dd>
                                                    <dt className="text-muted-foreground">{t('form.country')}</dt>
                                                    <dd>{form.data.country}</dd>
                                                    {form.data.website && (
                                                        <>
                                                            <dt className="text-muted-foreground">{t('form.website')}</dt>
                                                            <dd>{form.data.website}</dd>
                                                        </>
                                                    )}
                                                </dl>
                                            </div>

                                            <div className="rounded-lg border p-4">
                                                <h3 className="mb-2 font-semibold">{t('vendor.contact_person')}</h3>
                                                <dl className="grid grid-cols-2 gap-2 text-sm">
                                                    <dt className="text-muted-foreground">{t('form.name')}</dt>
                                                    <dd>{form.data.contact_person}</dd>
                                                    <dt className="text-muted-foreground">{t('form.email')}</dt>
                                                    <dd>{form.data.email}</dd>
                                                    <dt className="text-muted-foreground">{t('form.phone')}</dt>
                                                    <dd>{form.data.phone}</dd>
                                                    {form.data.whatsapp_number && (
                                                        <>
                                                            <dt className="text-muted-foreground">{t('form.whatsapp_number')}</dt>
                                                            <dd>{form.data.whatsapp_number}</dd>
                                                        </>
                                                    )}
                                                </dl>
                                            </div>

                                            <div className="rounded-lg border p-4">
                                                <h3 className="mb-2 font-semibold">{t('auth.selected_categories')}</h3>
                                                <p className="text-sm">
                                                    {form.data.category_ids.length} {t('auth.categories_selected')}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Step 5: Submit */}
                                {currentStep === 5 && (
                                    <div className="space-y-4 text-center py-8">
                                        <CardHeader className="p-0 pb-4">
                                            <CardTitle>{t('auth.ready_to_submit')}</CardTitle>
                                            <CardDescription>
                                                {t('auth.registration_review_message')}
                                            </CardDescription>
                                        </CardHeader>

                                        {form.errors && Object.keys(form.errors).length > 0 && (
                                            <div className="rounded-lg border border-destructive bg-destructive/10 p-4 text-sm text-destructive">
                                                <p className="font-semibold">{t('auth.fix_errors')}</p>
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
                                                {t('btn.previous')}
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
                                                {t('btn.next')}
                                                <ChevronRight className="ml-1 h-4 w-4" />
                                            </Button>
                                        ) : (
                                            <Button type="submit" disabled={form.processing}>
                                                {form.processing ? t('btn.submitting') : t('btn.submit_registration')}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <p className="mt-4 text-center text-sm text-muted-foreground">
                        {t('auth.already_have_account')}{' '}
                        <Link href="/vendor/login" className="text-primary underline">
                            {t('auth.sign_in')}
                        </Link>
                    </p>
                </div>
            </div>
        </>
    );
}
