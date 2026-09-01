import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Clock, Pencil, Plus, UserPlus, Users, X } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { StatTile } from '@/components/dashboard/StatTile';
import { SearchableSelect } from '@/components/SearchableSelect';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/datetime';

type Member = {
    id: string;
    name: string;
    email: string;
    pivot: { role: string; has_scored: boolean; scored_at: string | null };
};

type Committee = {
    id: string;
    name: string;
    committee_type: string;
    status: string;
    formed_at: string;
    members: Member[];
};

type Option = { value: string; labelKey: string };

type Props = {
    tender: { id: string; reference_number: string; title_en: string; is_two_envelope: boolean };
    committees: Committee[];
    projectUsers: Array<{ id: string; name: string; email: string }>;
    /** From the CommitteeType and CommitteeStatus enums, so the pickers cannot drift. */
    typeOptions: Option[];
    statusOptions: Option[];
    canManage: boolean;
};

const MEMBER_ROLES = ['chair', 'member', 'secretary'];

export default function Committees({
    tender,
    committees,
    projectUsers,
    typeOptions,
    statusOptions,
    canManage,
}: Props) {
    const { t, locale } = useTranslation();

    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [addMemberCommitteeId, setAddMemberCommitteeId] = useState<string | null>(null);
    const [editing, setEditing] = useState<Committee | null>(null);
    const [pendingRemoval, setPendingRemoval] = useState<{
        committeeId: string;
        userId: string;
        name: string;
    } | null>(null);

    const createForm = useForm({ name: '', committee_type: '' });
    const memberForm = useForm({ user_id: '', role: '' });
    const editForm = useForm({ name: '', status: '' });
    // useForm() used to be called inside the click handler. It is a React hook,
    // so invoking it outside render threw "Invalid hook call" and blew up the
    // page — removing a member was impossible.
    const removeForm = useForm({});

    const handleCreateCommittee = () => {
        createForm.post(`/tenders/${tender.id}/committees`, {
            preserveScroll: true,
            onSuccess: () => {
                setCreateDialogOpen(false);
                createForm.reset();
            },
        });
    };

    const handleAddMember = (committeeId: string) => {
        memberForm.post(`/tenders/${tender.id}/committees/${committeeId}/members`, {
            preserveScroll: true,
            onSuccess: () => {
                setAddMemberCommitteeId(null);
                memberForm.reset();
            },
        });
    };

    const openEdit = (committee: Committee) => {
        editForm.setData({ name: committee.name, status: committee.status });
        setEditing(committee);
    };

    const submitEdit = () => {
        if (!editing) {
            return;
        }

        editForm.put(`/tenders/${tender.id}/committees/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    const confirmRemoveMember = () => {
        if (!pendingRemoval) {
            return;
        }

        // The URL takes the USER id: members() is a belongsToMany over users and
        // never exposed a pivot id, so sending member.id as a CommitteeMember id
        // 404'd every time.
        removeForm.delete(
            `/tenders/${tender.id}/committees/${pendingRemoval.committeeId}/members/${pendingRemoval.userId}`,
            { preserveScroll: true, onFinish: () => setPendingRemoval(null) },
        );
    };

    const roleBadgeVariant = (role: string) => {
        switch (role) {
            case 'chair':
                return 'default' as const;
            case 'secretary':
                return 'secondary' as const;
            default:
                return 'outline' as const;
        }
    };

    const userOptions = projectUsers.map((u) => ({ value: u.id, label: `${u.name} (${u.email})` }));

    const allMembers = committees.flatMap((c) => c.members);
    const scoredMembers = allMembers.filter((m) => m.pivot.has_scored).length;
    const unstaffed = committees.filter((c) => c.members.length === 0).length;

    return (
        <>
            <Head title={`${t('eval.committees')} — ${tender.reference_number}`} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link
                            href={`/tenders/${tender.id}`}
                            className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4 rtl:rotate-180" aria-hidden="true" />
                            {tender.reference_number}
                        </Link>
                        <h1 className="mt-2 text-2xl font-semibold tracking-tight">
                            {t('pages.eval.evaluation_committees')}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">{tender.title_en}</p>
                    </div>
                    {canManage && (
                        <Button onClick={() => setCreateDialogOpen(true)}>
                            <Plus className="me-2 size-4" aria-hidden="true" />
                            {t('btn.create_committee')}
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label={t('eval.committees')}
                        value={String(committees.length)}
                        hint={t('eval.on_this_tender')}
                        icon={Users}
                    />
                    <StatTile
                        label={t('eval.evaluators')}
                        value={String(allMembers.length)}
                        hint={t('eval.across_all_committees')}
                        icon={UserPlus}
                    />
                    <StatTile
                        label={t('status.scored')}
                        value={String(scoredMembers)}
                        hint={t('eval.evaluators_finished')}
                        icon={Check}
                    />
                    {/* A committee with nobody on it cannot score, and nothing else
                        on the page says so at a glance. */}
                    <StatTile
                        label={t('eval.without_members')}
                        value={String(unstaffed)}
                        hint={t('eval.cannot_score_yet')}
                        icon={Clock}
                    />
                </div>

                {committees.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-14 text-center">
                            <Users className="size-10 text-muted-foreground" aria-hidden="true" />
                            <p className="mt-4 text-lg font-medium">{t('empty.no_committees')}</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t('empty.no_committees_description')}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-2">
                        {committees.map((committee) => (
                            <Card key={committee.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <CardTitle className="flex flex-wrap items-center gap-2">
                                            {committee.name}
                                            <Badge>{t(`eval.${committee.committee_type}`)}</Badge>
                                            <Badge variant="outline">
                                                {t(`status.${committee.status}`)}
                                            </Badge>
                                        </CardTitle>
                                        {canManage && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                onClick={() => openEdit(committee)}
                                                aria-label={t('btn.edit')}
                                            >
                                                <Pencil className="size-4" aria-hidden="true" />
                                            </Button>
                                        )}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {t('eval.formed')}: {formatDate(committee.formed_at, locale)}
                                    </p>
                                </CardHeader>

                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        {/* store() creates a committee with no members, so
                                            this is the state every one starts in. */}
                                        {committee.members.length === 0 && (
                                            <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                                                {t('eval.no_members_yet')}
                                            </p>
                                        )}

                                        {committee.members.map((member) => (
                                            <div
                                                key={member.id}
                                                className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
                                            >
                                                <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {member.name}
                                                        </p>
                                                        <p className="truncate text-xs text-muted-foreground">
                                                            {member.email}
                                                        </p>
                                                    </div>
                                                    <Badge variant={roleBadgeVariant(member.pivot.role)}>
                                                        {t(`eval.role_${member.pivot.role}`)}
                                                    </Badge>
                                                </div>

                                                <div className="flex items-center gap-2">
                                                    {member.pivot.has_scored ? (
                                                        <Badge className="border-transparent bg-emerald-600 text-white">
                                                            <Check
                                                                className="me-1 size-3"
                                                                aria-hidden="true"
                                                            />
                                                            {t('status.scored')}
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary">
                                                            <Clock
                                                                className="me-1 size-3"
                                                                aria-hidden="true"
                                                            />
                                                            {t('status.pending')}
                                                        </Badge>
                                                    )}
                                                    {canManage && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8 text-destructive"
                                                            aria-label={t('eval.remove_member')}
                                                            onClick={() =>
                                                                setPendingRemoval({
                                                                    committeeId: committee.id,
                                                                    userId: member.id,
                                                                    name: member.name,
                                                                })
                                                            }
                                                        >
                                                            <X className="size-4" aria-hidden="true" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    {canManage &&
                                        (addMemberCommitteeId === committee.id ? (
                                            <div className="space-y-3 rounded-lg border border-dashed p-4">
                                                <div className="space-y-2">
                                                    <Label>{t('form.user')}</Label>
                                                    <SearchableSelect
                                                        options={userOptions}
                                                        value={memberForm.data.user_id}
                                                        onChange={(value) =>
                                                            memberForm.setData('user_id', value)
                                                        }
                                                        placeholder={t('form.search_user')}
                                                    />
                                                    {memberForm.errors.user_id && (
                                                        <p className="text-sm text-destructive">
                                                            {memberForm.errors.user_id}
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="space-y-2">
                                                    <Label>{t('form.role')}</Label>
                                                    <Select
                                                        value={memberForm.data.role}
                                                        onValueChange={(value) =>
                                                            memberForm.setData('role', value)
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue
                                                                placeholder={t('form.select_role')}
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {MEMBER_ROLES.map((role) => (
                                                                <SelectItem key={role} value={role}>
                                                                    {t(`form.role_${role}`)}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    {memberForm.errors.role && (
                                                        <p className="text-sm text-destructive">
                                                            {memberForm.errors.role}
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        size="sm"
                                                        onClick={() => handleAddMember(committee.id)}
                                                        disabled={memberForm.processing}
                                                    >
                                                        {t('btn.add')}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            setAddMemberCommitteeId(null);
                                                            memberForm.reset();
                                                        }}
                                                    >
                                                        {t('btn.cancel')}
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-full"
                                                onClick={() => setAddMemberCommitteeId(committee.id)}
                                            >
                                                <UserPlus className="me-2 size-4" aria-hidden="true" />
                                                {t('btn.add_member')}
                                            </Button>
                                        ))}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('eval.create_committee')}</DialogTitle>
                        <DialogDescription>{t('eval.create_committee_description')}</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="committee-name">{t('form.name')}</Label>
                            <Input
                                id="committee-name"
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                            />
                            {createForm.errors.name && (
                                <p className="text-sm text-destructive">{createForm.errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>{t('eval.envelope')}</Label>
                            <Select
                                value={createForm.data.committee_type}
                                onValueChange={(value) => createForm.setData('committee_type', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={t('form.select_type')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {typeOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {t(option.labelKey)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {createForm.errors.committee_type && (
                                <p className="text-sm text-destructive">
                                    {createForm.errors.committee_type}
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCreateDialogOpen(false)}>
                            {t('btn.cancel')}
                        </Button>
                        <Button onClick={handleCreateCommittee} disabled={createForm.processing}>
                            {t('btn.create')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* tenders.committees.update existed but nothing in the app called it,
                so a committee could never be renamed or marked completed — and
                completion is the flag the evaluation workflow reads. */}
            <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('eval.edit_committee')}</DialogTitle>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="edit-committee-name">{t('form.name')}</Label>
                            <Input
                                id="edit-committee-name"
                                value={editForm.data.name}
                                onChange={(e) => editForm.setData('name', e.target.value)}
                            />
                            {editForm.errors.name && (
                                <p className="text-sm text-destructive">{editForm.errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>{t('form.status')}</Label>
                            <Select
                                value={editForm.data.status}
                                onValueChange={(value) => editForm.setData('status', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={t('form.select_status')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {statusOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {t(option.labelKey)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {editForm.errors.status && (
                                <p className="text-sm text-destructive">{editForm.errors.status}</p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditing(null)}>
                            {t('btn.cancel')}
                        </Button>
                        <Button onClick={submitEdit} disabled={editForm.processing}>
                            {t('btn.save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={pendingRemoval !== null}
                onOpenChange={(open) => !open && setPendingRemoval(null)}
                onConfirm={confirmRemoveMember}
                loading={removeForm.processing}
                title={t('eval.remove_member')}
                description={t('eval.remove_member_confirm', { name: pendingRemoval?.name ?? '' })}
            />
        </>
    );
}
