import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { DataTable } from '@/components/DataTable';
import { Plus, Lock, Pencil, Shield } from 'lucide-react';

type Role = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    permissions_count: number;
    users_count: number;
};

type Props = {
    roles: Role[];
};

export default function Index({ roles }: Props) {
    const { t } = useTranslation();
    const [createOpen, setCreateOpen] = useState(false);
    const [editRole, setEditRole] = useState<Role | null>(null);

    const createForm = useForm({
        name: '',
        slug: '',
        description: '',
    });

    const editForm = useForm({
        name: '',
        slug: '',
        description: '',
    });

    function openCreate() {
        createForm.reset();
        setCreateOpen(true);
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/admin/roles', {
            onSuccess: () => setCreateOpen(false),
        });
    }

    function openEdit(role: Role) {
        editForm.setData({
            name: role.name,
            slug: role.slug,
            description: role.description ?? '',
        });
        setEditRole(role);
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editRole) return;
        editForm.put(`/admin/roles/${editRole.id}`, {
            onSuccess: () => setEditRole(null),
        });
    }

    const columns = [
        {
            key: 'name',
            label: t('table.name'),
            render: (value: string) => (
                <div className="flex items-center gap-2">
                    <Shield className="h-4 w-4 text-muted-foreground" />
                    <span className="font-medium">{value}</span>
                </div>
            ),
        },
        { key: 'slug', label: t('table.slug') },
        {
            key: 'description',
            label: t('table.description'),
            render: (value: string | null) => (
                <span className="text-muted-foreground">{value ?? '—'}</span>
            ),
        },
        {
            key: 'permissions_count',
            label: t('table.permissions'),
            render: (value: number) => <Badge variant="secondary">{value}</Badge>,
        },
        {
            key: 'users_count',
            label: t('table.users'),
            render: (value: number) => <Badge variant="outline">{value}</Badge>,
        },
        {
            key: 'is_system',
            label: t('table.system'),
            render: (value: boolean) => value ? <Lock className="h-4 w-4 text-amber-500" /> : null,
        },
    ];

    return (
        <>
            <Head title="Roles" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading title={t('pages.admin.roles')} description={t('pages.admin.roles_description')} />
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('btn.create_role')}
                    </Button>
                </div>

                <DataTable
                    columns={columns}
                    data={{ data: roles }}
                    actions={(role: Role) => (
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={`/admin/roles/${role.id}/permissions`}>{t('btn.manage_permissions')}</Link>
                            </Button>
                            {!role.is_system && (
                                <Button variant="ghost" size="sm" onClick={() => openEdit(role)}>
                                    <Pencil className="h-4 w-4" />
                                </Button>
                            )}
                        </div>
                    )}
                />
            </div>

            {/* Create Dialog */}
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('pages.admin.create_role')}</DialogTitle>
                        <DialogDescription>{t('pages.admin.create_role_description')}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitCreate} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="create-name">{t('form.name')}</Label>
                            <Input
                                id="create-name"
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                            />
                            {createForm.errors.name && (
                                <p className="text-sm text-destructive">{createForm.errors.name}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="create-slug">{t('form.slug')}</Label>
                            <Input
                                id="create-slug"
                                value={createForm.data.slug}
                                onChange={(e) => createForm.setData('slug', e.target.value)}
                            />
                            {createForm.errors.slug && (
                                <p className="text-sm text-destructive">{createForm.errors.slug}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="create-description">{t('form.description')}</Label>
                            <Input
                                id="create-description"
                                value={createForm.data.description}
                                onChange={(e) => createForm.setData('description', e.target.value)}
                            />
                            {createForm.errors.description && (
                                <p className="text-sm text-destructive">{createForm.errors.description}</p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                {t('btn.cancel')}
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                {t('btn.create')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={!!editRole} onOpenChange={(open) => !open && setEditRole(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('pages.admin.edit_role')}</DialogTitle>
                        <DialogDescription>{t('pages.admin.edit_role_description')}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitEdit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="edit-name">{t('form.name')}</Label>
                            <Input
                                id="edit-name"
                                value={editForm.data.name}
                                onChange={(e) => editForm.setData('name', e.target.value)}
                            />
                            {editForm.errors.name && (
                                <p className="text-sm text-destructive">{editForm.errors.name}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-slug">{t('form.slug')}</Label>
                            <Input
                                id="edit-slug"
                                value={editForm.data.slug}
                                onChange={(e) => editForm.setData('slug', e.target.value)}
                            />
                            {editForm.errors.slug && (
                                <p className="text-sm text-destructive">{editForm.errors.slug}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-description">{t('form.description')}</Label>
                            <Input
                                id="edit-description"
                                value={editForm.data.description}
                                onChange={(e) => editForm.setData('description', e.target.value)}
                            />
                            {editForm.errors.description && (
                                <p className="text-sm text-destructive">{editForm.errors.description}</p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditRole(null)}>
                                {t('btn.cancel')}
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                {t('btn.save_changes')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
