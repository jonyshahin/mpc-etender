import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { SearchableSelect } from '@/components/SearchableSelect';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Plus, X, Check, Clock, Users } from 'lucide-react';

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

type Props = {
    tender: { id: string; reference_number: string; title_en: string; is_two_envelope: boolean };
    committees: Committee[];
    projectUsers: Array<{ id: string; name: string; email: string }>;
};

export default function Committees({ tender, committees, projectUsers }: Props) {
    const { t } = useTranslation();
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [addMemberCommitteeId, setAddMemberCommitteeId] = useState<string | null>(null);

    const createForm = useForm({ name: '', committee_type: '' });
    const memberForm = useForm({ user_id: '', role: '' });

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

    const handleRemoveMember = (committeeId: string, memberId: string) => {
        if (!confirm('Remove this member from the committee?')) return;
        const form = useForm({});
        form.delete(`/tenders/${tender.id}/committees/${committeeId}/members/${memberId}`, {
            preserveScroll: true,
        });
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

    return (
        <>
            <Head title={`Committees - ${tender.reference_number}`} />
            <Heading title={t('pages.eval.evaluation_committees')} description={`${tender.reference_number} - ${tender.title_en}`} />

            <div className="mt-6 space-y-6">
                <div className="flex justify-end">
                    <Button onClick={() => setCreateDialogOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('btn.create_committee')}
                    </Button>
                </div>

                {committees.length === 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Users className="h-12 w-12 text-muted-foreground" />
                            <p className="mt-4 text-lg font-medium">{t('empty.no_committees')}</p>
                            <p className="text-sm text-muted-foreground">
                                {t('empty.no_committees_description')}
                            </p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 md:grid-cols-2">
                    {committees.map((committee) => (
                        <Card key={committee.id}>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        {committee.name}
                                        <Badge>{committee.committee_type}</Badge>
                                    </CardTitle>
                                    <Badge variant="outline">{committee.status}</Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {t('eval.formed')}: {new Date(committee.formed_at).toLocaleDateString()}
                                </p>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    {committee.members.map((member) => (
                                        <div
                                            key={member.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div>
                                                    <p className="text-sm font-medium">{member.name}</p>
                                                    <p className="text-xs text-muted-foreground">{member.email}</p>
                                                </div>
                                                <Badge variant={roleBadgeVariant(member.pivot.role)}>
                                                    {member.pivot.role}
                                                </Badge>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {member.pivot.has_scored ? (
                                                    <Badge variant="default" className="bg-green-600">
                                                        <Check className="mr-1 h-3 w-3" />
                                                        {t('status.scored')}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        <Clock className="mr-1 h-3 w-3" />
                                                        {t('status.pending')}
                                                    </Badge>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 text-destructive"
                                                    onClick={() => handleRemoveMember(committee.id, member.id)}
                                                >
                                                    <X className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {addMemberCommitteeId === committee.id ? (
                                    <div className="space-y-3 rounded-lg border border-dashed p-4">
                                        <div className="space-y-2">
                                            <Label>{t('form.user')}</Label>
                                            <SearchableSelect
                                                options={userOptions}
                                                value={memberForm.data.user_id}
                                                onChange={(value) => memberForm.setData('user_id', value)}
                                                placeholder={t('form.search_user')}
                                            />
                                            {memberForm.errors.user_id && (
                                                <p className="text-sm text-destructive">{memberForm.errors.user_id}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('form.role')}</Label>
                                            <Select
                                                value={memberForm.data.role}
                                                onValueChange={(value) => memberForm.setData('role', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder={t('form.select_role')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="chair">{t('form.role_chair')}</SelectItem>
                                                    <SelectItem value="member">{t('form.role_member')}</SelectItem>
                                                    <SelectItem value="secretary">{t('form.role_secretary')}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {memberForm.errors.role && (
                                                <p className="text-sm text-destructive">{memberForm.errors.role}</p>
                                            )}
                                        </div>
                                        <div className="flex gap-2">
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
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('btn.add_member')}
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>

            <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('eval.create_committee')}</DialogTitle>
                        <DialogDescription>
                            {t('eval.create_committee_description')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>{t('form.committee_name')}</Label>
                            <Input
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                                placeholder={t('form.committee_name_placeholder')}
                            />
                            {createForm.errors.name && (
                                <p className="text-sm text-destructive">{createForm.errors.name}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label>{t('form.type')}</Label>
                            <Select
                                value={createForm.data.committee_type}
                                onValueChange={(value) => createForm.setData('committee_type', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={t('form.select_type')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="technical">{t('eval.technical')}</SelectItem>
                                    <SelectItem value="financial">{t('eval.financial')}</SelectItem>
                                    <SelectItem value="combined">{t('eval.combined')}</SelectItem>
                                </SelectContent>
                            </Select>
                            {createForm.errors.committee_type && (
                                <p className="text-sm text-destructive">{createForm.errors.committee_type}</p>
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
        </>
    );
}
