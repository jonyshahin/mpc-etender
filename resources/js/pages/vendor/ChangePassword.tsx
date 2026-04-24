import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
            onSuccess: () => form.reset('current_password', 'password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title={t('page.vendor_change_password_title')} />

            <div className="max-w-xl space-y-4">
                <Heading
                    title={t('page.vendor_change_password_title')}
                    description={t('page.vendor_change_password_desc')}
                />

                {mustChangePassword && (
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>{t('alert.must_change_password_title')}</AlertTitle>
                        <AlertDescription>{t('alert.must_change_password_desc')}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('page.vendor_change_password_title')}</CardTitle>
                        <CardDescription>{t('page.vendor_change_password_desc')}</CardDescription>
                    </CardHeader>

                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="current_password">{t('form.current_password')}</Label>
                                <Input
                                    id="current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                    value={form.data.current_password}
                                    onChange={(e) => form.setData('current_password', e.target.value)}
                                />
                                {form.errors.current_password && (
                                    <p className="text-sm text-destructive">{form.errors.current_password}</p>
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
                                    onChange={(e) => form.setData('password', e.target.value)}
                                />
                                {form.errors.password && (
                                    <p className="text-sm text-destructive">{form.errors.password}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password_confirmation">{t('form.confirm_password')}</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    required
                                    value={form.data.password_confirmation}
                                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
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
