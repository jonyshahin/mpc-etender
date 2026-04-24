import { Head, Link, useForm } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

export default function Login() {
    const { t } = useTranslation();
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/vendor/login');
    };

    return (
        <>
            <Head title={t('auth.vendor_login')} />

            <div className="flex flex-col gap-6">
                <div className="space-y-2 text-center">
                    <h1 className="text-xl font-medium">{t('auth.vendor_portal')}</h1>
                    <p className="text-sm text-muted-foreground">{t('auth.sign_in_description')}</p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {form.errors && Object.keys(form.errors).length > 0 && (
                        <div className="rounded-lg border border-destructive bg-destructive/10 p-3 text-sm text-destructive">
                            {Object.values(form.errors).map((error, i) => (
                                <p key={i}>{error}</p>
                            ))}
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="email">{t('form.email')}</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            placeholder="vendor@example.com"
                        />
                        {form.errors.email && (
                            <p className="text-sm text-destructive">{form.errors.email}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="password">{t('form.password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="current-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                        {form.errors.password && (
                            <p className="text-sm text-destructive">{form.errors.password}</p>
                        )}
                    </div>

                    <div className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="remember"
                                checked={form.data.remember}
                                onCheckedChange={(checked) => form.setData('remember', checked === true)}
                            />
                            <Label htmlFor="remember" className="cursor-pointer text-sm">
                                {t('auth.remember_me')}
                            </Label>
                        </div>
                        <Link
                            href="/vendor/forgot-password"
                            className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            {t('form.forgot_password')}
                        </Link>
                    </div>

                    <Button type="submit" className="w-full" disabled={form.processing}>
                        <LogIn className="me-2 h-4 w-4" />
                        {form.processing ? t('auth.signing_in') : t('auth.sign_in')}
                    </Button>
                </form>

                <p className="text-center text-sm text-muted-foreground">
                    {t('auth.no_account')}{' '}
                    <Link href="/vendor/register" className="text-primary underline">
                        {t('auth.register_here')}
                    </Link>
                </p>
            </div>
        </>
    );
}
