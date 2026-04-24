// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { useTranslation } from '@/hooks/use-translation';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();
    return (
        <>
            <Head title={t('auth.staff_verify_email_title')} />

            <div className="mb-6 space-y-2 text-center">
                <h1 className="text-xl font-medium">{t('auth.staff_verify_email_title')}</h1>
                <p className="text-sm text-muted-foreground">{t('auth.staff_verify_email_description')}</p>
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {t('auth.verification_link_sent')}
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            {t('auth.resend_verification')}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {t('auth.log_out')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

