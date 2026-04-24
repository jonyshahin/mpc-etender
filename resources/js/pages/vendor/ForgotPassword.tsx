import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ForgotPassword() {
    const { t } = useTranslation();
    const form = useForm({ email: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/vendor/forgot-password');
    };

    return (
        <>
            <Head title={t('page.vendor_forgot_password_title')} />

            <Card>
                <CardHeader>
                    <CardTitle>{t('page.vendor_forgot_password_title')}</CardTitle>
                    <CardDescription>{t('page.vendor_forgot_password_desc')}</CardDescription>
                </CardHeader>

                <CardContent>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="email">{t('form.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                autoFocus
                                required
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                placeholder={t('form.email_your_registered_email')}
                            />
                            {form.errors.email && (
                                <p className="text-sm text-destructive">{form.errors.email}</p>
                            )}
                        </div>

                        <div className="flex items-center justify-between gap-2">
                            <Link
                                href="/vendor/login"
                                className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            >
                                {t('btn.back_to_login')}
                            </Link>
                            <Button type="submit" disabled={form.processing || !form.data.email}>
                                {form.processing ? t('btn.sending') : t('form.send_reset_link')}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
