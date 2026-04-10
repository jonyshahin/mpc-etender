import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { MultiSelect } from '@/components/MultiSelect';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    user?: {
        id: string;
        name: string;
        email: string;
        role_id: string;
        phone: string | null;
        language_pref: string;
        is_active: boolean;
        role?: { id: string; name: string };
    };
    userProjectIds?: string[];
    roles: Array<{ id: string; name: string; slug: string }>;
    projects?: Array<{ id: string; name: string; code: string }>;
    /** Dialog mode props */
    open?: boolean;
    onClose?: () => void;
};

function UserForm({ user, userProjectIds, roles, projects, onClose }: Props) {
    const isEdit = !!user;

    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        phone: user?.phone ?? '',
        role_id: user?.role_id ?? '',
        language_pref: user?.language_pref ?? 'en',
        is_active: user?.is_active ?? true,
        project_ids: userProjectIds ?? [],
    });

    const projectOptions = (projects ?? []).map((p) => ({
        value: p.id,
        label: `${p.code} - ${p.name}`,
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            form.put(`/admin/users/${user.id}`, {
                onSuccess: () => onClose?.(),
            });
        } else {
            form.post('/admin/users', {
                onSuccess: () => onClose?.(),
            });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
                {form.errors.name && (
                    <p className="text-sm text-destructive">{form.errors.name}</p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
                {form.errors.email && (
                    <p className="text-sm text-destructive">{form.errors.email}</p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="password">
                    Password{isEdit && ' (leave blank to keep current)'}
                </Label>
                <Input
                    id="password"
                    type="password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                />
                {form.errors.password && (
                    <p className="text-sm text-destructive">{form.errors.password}</p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="phone">Phone</Label>
                <Input
                    id="phone"
                    value={form.data.phone}
                    onChange={(e) => form.setData('phone', e.target.value)}
                />
                {form.errors.phone && (
                    <p className="text-sm text-destructive">{form.errors.phone}</p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="role_id">Role</Label>
                <Select
                    value={form.data.role_id}
                    onValueChange={(value) => form.setData('role_id', value)}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select a role" />
                    </SelectTrigger>
                    <SelectContent>
                        {roles.map((role) => (
                            <SelectItem key={role.id} value={role.id}>
                                {role.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {form.errors.role_id && (
                    <p className="text-sm text-destructive">{form.errors.role_id}</p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="language_pref">Language</Label>
                <Select
                    value={form.data.language_pref}
                    onValueChange={(value) => form.setData('language_pref', value)}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select language" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="en">English</SelectItem>
                        <SelectItem value="ar">Arabic</SelectItem>
                    </SelectContent>
                </Select>
                {form.errors.language_pref && (
                    <p className="text-sm text-destructive">{form.errors.language_pref}</p>
                )}
            </div>

            {isEdit && (
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="is_active"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                    />
                    <Label htmlFor="is_active">Active</Label>
                </div>
            )}

            {projectOptions.length > 0 && (
                <div className="space-y-2">
                    <Label>Assigned Projects</Label>
                    <MultiSelect
                        options={projectOptions}
                        value={form.data.project_ids}
                        onChange={(value: string[]) => form.setData('project_ids', value)}
                        placeholder="Select projects..."
                    />
                    {form.errors.project_ids && (
                        <p className="text-sm text-destructive">{form.errors.project_ids}</p>
                    )}
                </div>
            )}

            <div className="flex justify-end gap-2 pt-4">
                {onClose && (
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                )}
                <Button type="submit" disabled={form.processing}>
                    {isEdit ? 'Update User' : 'Create User'}
                </Button>
            </div>
        </form>
    );
}

/**
 * Dialog wrapper for create mode (used from Index page).
 */
export function UserFormDialog({
    roles,
    projects,
    open,
    onClose,
}: {
    roles: Props['roles'];
    projects?: Props['projects'];
    open: boolean;
    onClose: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create User</DialogTitle>
                </DialogHeader>
                <UserForm roles={roles} projects={projects} onClose={onClose} />
            </DialogContent>
        </Dialog>
    );
}

/**
 * Full-page edit mode (rendered by Inertia as admin/Users/Form).
 */
export default function Form(props: Props) {
    return (
        <>
            <Head title={props.user ? 'Edit User' : 'Create User'} />
            <Heading
                title={props.user ? 'Edit User' : 'Create User'}
                description={
                    props.user
                        ? `Editing ${props.user.name}`
                        : 'Create a new user account.'
                }
            />
            <div className="mt-6 max-w-2xl">
                <UserForm {...props} />
            </div>
        </>
    );
}
