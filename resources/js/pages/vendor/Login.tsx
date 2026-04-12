import { Head, useForm, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { LogIn } from 'lucide-react';
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
            <Head title="Vendor Login" />

            <div className="flex min-h-screen items-center justify-center bg-background p-4">
                <div className="w-full max-w-md">
                    <Card>
                        <CardHeader className="text-center">
                            <CardTitle className="text-2xl">{t('auth.vendor_portal')}</CardTitle>
                            <CardDescription>{t('auth.sign_in_description')}</CardDescription>
                        </CardHeader>
                        <CardContent>
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

                                <Button type="submit" className="w-full" disabled={form.processing}>
                                    <LogIn className="mr-2 h-4 w-4" />
                                    {form.processing ? t('auth.signing_in') : t('auth.sign_in')}
                                </Button>
                            </form>

                            <p className="mt-4 text-center text-sm text-muted-foreground">
                                {t('auth.no_account')}{' '}
                                <Link href="/vendor/register" className="text-primary underline">
                                    {t('auth.register_here')}
                                </Link>
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
