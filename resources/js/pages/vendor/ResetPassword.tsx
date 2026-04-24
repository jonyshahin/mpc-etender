import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

interface Props {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/vendor/reset-password');
    };

    return (
        <>
            <Head title={t('page.vendor_reset_password_title')} />

            <div className="flex flex-col gap-6">
                <div className="space-y-2 text-center">
                    <h1 className="text-xl font-medium">{t('page.vendor_reset_password_title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('page.vendor_reset_password_desc')}</p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="email">{t('form.email')}</Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            readOnly
                            className="bg-muted"
                        />
                        {form.errors.email && (
                            <p className="text-sm text-destructive">{form.errors.email}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="password">{t('form.new_password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            autoFocus
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

                    <Button type="submit" disabled={form.processing} className="w-full">
                        {form.processing ? t('btn.saving') : t('btn.reset_password')}
                    </Button>
                </form>
            </div>
        </>
    );
}
