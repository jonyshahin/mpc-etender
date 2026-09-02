import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, ShieldCheck } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

interface Props {
    mustChangePassword: boolean;
}

export default function ChangePassword({ mustChangePassword }: Props) {
    const { t } = useTranslation();

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/vendor/password/change', {
            preserveScroll: true,
            onSuccess: () =>
                form.reset('current_password', 'password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title={t('page.vendor_change_password_title')} />

            <div className="max-w-xl space-y-4">
                {/* Suppressed while the password change is forced: the middleware
                    bounces every other vendor route back here, so a "back to
                    profile" link would be a door that does not open. */}
                {!mustChangePassword && (
                    <Button asChild variant="ghost" size="sm" className="-ms-2">
                        <Link href="/vendor/profile">
                            <ArrowLeft className="me-2 size-4 rtl:rotate-180" aria-hidden="true" />
                            {t('btn.back_to_profile')}
                        </Link>
                    </Button>
                )}

                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('page.vendor_change_password_title')}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('page.vendor_change_password_desc')}
                    </p>
                </div>

                {mustChangePassword && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" aria-hidden="true" />
                        <AlertTitle>{t('alert.must_change_password_title')}</AlertTitle>
                        <AlertDescription>{t('alert.must_change_password_desc')}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ShieldCheck className="size-4" aria-hidden="true" />
                            {t('page.vendor_change_password_title')}
                        </CardTitle>
                        <CardDescription>{t('form.password_requirements')}</CardDescription>
                    </CardHeader>

                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="current_password">
                                    {t('form.current_password')}
                                </Label>
                                <Input
                                    id="current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                    value={form.data.current_password}
                                    aria-invalid={form.errors.current_password ? true : undefined}
                                    onChange={(e) =>
                                        form.setData('current_password', e.target.value)
                                    }
                                />
                                {form.errors.current_password && (
                                    <p role="alert" className="text-sm text-destructive">
                                        {form.errors.current_password}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">{t('form.new_password')}</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    required
                                    value={form.data.password}
                                    aria-invalid={form.errors.password ? true : undefined}
                                    aria-describedby="password-hint"
                                    onChange={(e) => form.setData('password', e.target.value)}
                                />
                                <p id="password-hint" className="text-xs text-muted-foreground">
                                    {t('form.password_requirements')}
                                </p>
                                {form.errors.password && (
                                    <p role="alert" className="text-sm text-destructive">
                                        {form.errors.password}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password_confirmation">
                                    {t('form.confirm_password')}
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    required
                                    value={form.data.password_confirmation}
                                    onChange={(e) =>
                                        form.setData('password_confirmation', e.target.value)
                                    }
                                />
                            </div>

                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? t('btn.saving') : t('btn.change_password')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
