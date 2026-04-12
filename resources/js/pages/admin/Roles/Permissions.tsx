import { Head, Link, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ArrowLeft, Save } from 'lucide-react';

type Permission = {
    id: string;
    name: string;
    slug: string;
    module: string;
    description: string | null;
};

type Props = {
    role: { id: string; name: string; slug: string; is_system: boolean };
    permissions: Permission[];
    rolePermissionIds: string[];
};

const MODULE_ORDER = ['vendors', 'tenders', 'bids', 'evaluations', 'reports', 'admin', 'approvals'];

export default function Permissions({ role, permissions, rolePermissionIds }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        permission_ids: [...rolePermissionIds],
    });

    const grouped = permissions.reduce<Record<string, Permission[]>>((acc, perm) => {
        if (!acc[perm.module]) acc[perm.module] = [];
        acc[perm.module].push(perm);
        return acc;
    }, {});

    const sortedModules = Object.keys(grouped).sort((a, b) => {
        const ai = MODULE_ORDER.indexOf(a);
        const bi = MODULE_ORDER.indexOf(b);
        return (ai === -1 ? 999 : ai) - (bi === -1 ? 999 : bi);
    });

    function togglePermission(id: string) {
        const current = form.data.permission_ids;
        if (current.includes(id)) {
            form.setData('permission_ids', current.filter((pid) => pid !== id));
        } else {
            form.setData('permission_ids', [...current, id]);
        }
    }

    function toggleModule(module: string) {
        const moduleIds = grouped[module].map((p) => p.id);
        const allChecked = moduleIds.every((id) => form.data.permission_ids.includes(id));
        if (allChecked) {
            form.setData(
                'permission_ids',
                form.data.permission_ids.filter((id) => !moduleIds.includes(id)),
            );
        } else {
            const merged = new Set([...form.data.permission_ids, ...moduleIds]);
            form.setData('permission_ids', Array.from(merged));
        }
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/admin/roles/${role.id}/permissions`);
    }

    return (
        <>
            <Head title={`Permissions - ${role.name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/admin/roles">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                {t('btn.back')}
                            </Link>
                        </Button>
                        <Heading
                            title={`${t('pages.admin.permissions_for')} ${role.name}`}
                            description={`${t('pages.admin.permissions_description', { role: role.name })}`}
                        />
                    </div>
                    <Button onClick={handleSubmit} disabled={form.processing}>
                        <Save className="mr-2 h-4 w-4" />
                        {t('btn.save_permissions')}
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {sortedModules.map((module) => {
                        const modulePerms = grouped[module];
                        const moduleIds = modulePerms.map((p) => p.id);
                        const allChecked = moduleIds.every((id) =>
                            form.data.permission_ids.includes(id),
                        );
                        const someChecked =
                            !allChecked &&
                            moduleIds.some((id) => form.data.permission_ids.includes(id));

                        return (
                            <Card key={module}>
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            checked={allChecked ? true : someChecked ? 'indeterminate' : false}
                                            onCheckedChange={() => toggleModule(module)}
                                            disabled={role.is_system}
                                        />
                                        <CardTitle className="capitalize">{module}</CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {modulePerms.map((perm) => (
                                            <label
                                                key={perm.id}
                                                className="flex items-start gap-3 rounded-md border p-3 hover:bg-muted/50"
                                            >
                                                <Checkbox
                                                    checked={form.data.permission_ids.includes(perm.id)}
                                                    onCheckedChange={() => togglePermission(perm.id)}
                                                    disabled={role.is_system}
                                                    className="mt-0.5"
                                                />
                                                <div className="space-y-1">
                                                    <p className="text-sm font-medium leading-none">
                                                        {perm.name}
                                                    </p>
                                                    {perm.description && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {perm.description}
                                                        </p>
                                                    )}
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </form>
            </div>
        </>
    );
}
