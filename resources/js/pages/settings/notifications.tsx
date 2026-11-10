// What the owner is told about, where, and quiet hours. FR-NTF-04, FR-NTF-06, FR-PRO-06, FR-PRO-09. Preview.
import { BellRing, Mail, MonitorSmartphone, Moon, Sunrise } from 'lucide-react';
import { PlannedNotice, PreviewCard } from '@/components/planned';
import { SettingsLayout } from '@/layouts/settings-layout';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';

const events = ['orders', 'stock', 'approvals', 'alerts', 'brief'];
const channels = [
    { key: 'app', icon: BellRing },
    { key: 'email', icon: Mail },
    { key: 'push', icon: MonitorSmartphone },
];
// Sample matrix: which channel is on for which event.
const on: Record<string, string[]> = { orders: ['app'], stock: ['app', 'email'], approvals: ['app', 'email'], alerts: ['app', 'email', 'push'], brief: ['email'] };

export default function NotificationSettings() {
    const t = useT();
    return (
        <SettingsLayout title={t('notify.title')} description={t('notify.subtitle')}>
            <PlannedNotice requirements={['FR-NTF-04', 'FR-NTF-06', 'FR-PRO-06', 'FR-PRO-09']} />
            <PreviewCard title={t('notify.what')}>
                <div className="relative -mx-5 overflow-x-auto sm:-mx-6">
                    <table className="w-full min-w-[28rem] text-sm">
                        <caption className="sr-only">{t('notify.what')}</caption>
                        <thead>
                            <tr className="border-b border-line text-caption text-ink-muted">
                                <th scope="col" className="px-5 py-2.5 text-start font-medium sm:px-6">
                                    {t('notify.col_event')}
                                </th>
                                {channels.map(({ key, icon: Icon }) => (
                                    <th key={key} scope="col" className="px-3 py-2.5 text-center font-medium">
                                        <span className="inline-flex items-center gap-1.5">
                                            <Icon className="h-4 w-4" aria-hidden />
                                            {t(`notify.ch_${key}`)}
                                        </span>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {events.map((e) => (
                                <tr key={e} className="border-b border-line last:border-b-0">
                                    <th scope="row" className="px-5 py-3 text-start font-normal sm:px-6">
                                        {t(`notify.ev_${e}`)}
                                    </th>
                                    {channels.map(({ key }) => {
                                        const checked = on[e].includes(key);
                                        return (
                                            <td key={key} className="px-3 py-3 text-center">
                                                <span
                                                    role="img"
                                                    aria-label={checked ? t('notify.on') : t('notify.off')}
                                                    className={cn('inline-block h-5 w-5 rounded-md border-[1.5px]', checked ? 'border-brand-accent bg-brand-accent' : 'border-line-control')}
                                                />
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </PreviewCard>
            <div className="grid gap-6 sm:grid-cols-2">
                <PreviewCard title={t('notify.brief')}>
                    <p className="flex items-center gap-3 text-sm">
                        <Sunrise className="h-5 w-5 text-ink-muted" aria-hidden />
                        <span>
                            {t('notify.brief_at')}{' '}
                            <span dir="ltr" className="tabular font-semibold">
                                09:00
                            </span>
                        </span>
                    </p>
                </PreviewCard>
                <PreviewCard title={t('notify.quiet')}>
                    <p className="flex items-center gap-3 text-sm">
                        <Moon className="h-5 w-5 text-ink-muted" aria-hidden />
                        <span dir="ltr" className="tabular font-semibold">
                            23:00 – 08:00
                        </span>
                    </p>
                    <p className="mt-2 text-caption text-ink-muted">{t('notify.quiet_hint')}</p>
                </PreviewCard>
            </div>
        </SettingsLayout>
    );
}
