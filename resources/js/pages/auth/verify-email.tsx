import { useForm } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardBody } from '@/components/ui/card';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import { useT } from '@/lib/i18n';

/** FR-ONB-07: verification runs in the background; this page only resends the link. */
export default function VerifyEmail({ email }: { email: string }) {
    const t = useT();
    const { post, processing } = useForm({});

    return (
        <DashboardLayout title={t('verify.title')}>
            <Card className="mx-auto max-w-lg">
                <CardBody className="py-8 text-center">
                    <span className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-accent-subtle text-primary">
                        <MailCheck className="h-6 w-6" aria-hidden />
                    </span>
                    <h1 className="mt-4 text-xl font-semibold">{t('verify.title')}</h1>
                    <p className="mt-2 text-ink-muted">{t('verify.body', { email })}</p>
                    <Button variant="secondary" className="mt-6" disabled={processing} onClick={() => post('/email/verification-notification')}>
                        {t('verify.resend')}
                    </Button>
                </CardBody>
            </Card>
        </DashboardLayout>
    );
}
