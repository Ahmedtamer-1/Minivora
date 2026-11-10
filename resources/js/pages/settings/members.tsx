// Team: invite by email with a role, change role, revoke. FR-ACC-04
import { router, useForm, usePage } from '@inertiajs/react';
import { Mail, Trash2, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/input';
import { SettingsLayout } from '@/layouts/settings-layout';
import { formatDate, useT } from '@/lib/i18n';
import type { Role, SharedProps } from '@/types';

interface Member {
    id: number;
    name: string;
    email: string;
    role: Role;
    is_you: boolean;
}

interface PendingInvite {
    id: number;
    email: string;
    role: Role;
    expires_at: string;
}

interface Props {
    members: Member[];
    invitations: PendingInvite[];
    roles: Role[];
    invitableRoles: Role[];
}

export default function Members({ members, invitations, roles, invitableRoles }: Props) {
    const t = useT();
    const { locale, errors: pageErrors } = usePage<SharedProps>().props;
    const invite = useForm({ email: '', role: invitableRoles[0] ?? 'staff' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        invite.post('/settings/members', { preserveScroll: true, onSuccess: () => invite.reset('email') });
    };

    return (
        <SettingsLayout title={t('members.title')} description={t('members.subtitle')}>
                <Card>
                    <CardHeader title={t('members.invite')} />
                    <CardBody>
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                            <Field label={t('members.email')} htmlFor="invite_email" error={invite.errors.email} className="min-w-60 flex-1">
                                <Input id="invite_email" type="email" dir="ltr" value={invite.data.email} onChange={(e) => invite.setData('email', e.target.value)} required />
                            </Field>
                            <Field label={t('members.role')} htmlFor="invite_role" error={invite.errors.role} className="w-44">
                                <Select id="invite_role" value={invite.data.role} onChange={(e) => invite.setData('role', e.target.value as Role)}>
                                    {invitableRoles.map((r) => (
                                        <option key={r} value={r}>
                                            {t(`roles.${r}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Button type="submit" disabled={invite.processing}>
                                <UserPlus className="h-4 w-4" aria-hidden />
                                {t('members.invite')}
                            </Button>
                        </form>
                    </CardBody>
                </Card>

                <Card>
                    {pageErrors.member ? <p className="px-5 pt-4 text-sm text-danger" role="alert">{pageErrors.member}</p> : null}
                    <ul className="divide-y divide-line">
                        {members.map((m) => (
                            <li key={m.id} className="flex flex-wrap items-center gap-3 px-5 py-3">
                                <span aria-hidden className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent-subtle font-semibold text-primary">
                                    {m.name.charAt(0).toUpperCase()}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">
                                        {m.name} {m.is_you ? <Badge className="ms-1">{t('members.you')}</Badge> : null}
                                    </p>
                                    <p className="truncate text-sm text-ink-muted" dir="ltr">
                                        {m.email}
                                    </p>
                                </div>
                                <label className="sr-only" htmlFor={`role-${m.id}`}>
                                    {t('members.role')}
                                </label>
                                <Select
                                    id={`role-${m.id}`}
                                    className="w-40"
                                    value={m.role}
                                    onChange={(e) => router.put(`/settings/members/${m.id}`, { role: e.target.value }, { preserveScroll: true })}
                                >
                                    {roles.map((r) => (
                                        <option key={r} value={r}>
                                            {t(`roles.${r}`)}
                                        </option>
                                    ))}
                                </Select>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    aria-label={t('common.remove')}
                                    onClick={() => {
                                        if (window.confirm(t('members.remove_confirm', { name: m.name }))) {
                                            router.delete(`/settings/members/${m.id}`, { preserveScroll: true });
                                        }
                                    }}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                </Card>

                {invitations.length > 0 ? (
                    <Card>
                        <CardHeader title={t('members.pending')} />
                        <ul className="divide-y divide-line">
                            {invitations.map((i) => (
                                <li key={i.id} className="flex flex-wrap items-center gap-3 px-5 py-3">
                                    <Mail className="h-4 w-4 text-ink-muted" aria-hidden />
                                    <span className="min-w-0 flex-1 truncate" dir="ltr">
                                        {i.email}
                                    </span>
                                    <Badge tone="info">{t(`roles.${i.role}`)}</Badge>
                                    <span className="text-xs text-ink-muted">{t('members.expires', { date: formatDate(i.expires_at, locale) })}</span>
                                    <Button variant="ghost" size="sm" onClick={() => router.delete(`/settings/invitations/${i.id}`, { preserveScroll: true })}>
                                        {t('members.cancel_invite')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </Card>
                ) : null}
        </SettingsLayout>
    );
}
