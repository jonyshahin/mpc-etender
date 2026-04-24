import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { useTranslation } from '@/hooks/use-translation';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    const { t } = useTranslation();
    return (
        <>
            <Head title={t('auth.staff_confirm_password_title')} />

            <div className="mb-6 space-y-2 text-center">
                <h1 className="text-xl font-medium">{t('auth.staff_confirm_password_title')}</h1>
                <p className="text-sm text-muted-foreground">{t('auth.staff_confirm_password_description')}</p>
            </div>

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('auth.password')}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Password"
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.confirm_password')}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

