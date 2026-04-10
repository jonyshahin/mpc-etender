import { Head, useForm, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Trash2, UserPlus } from 'lucide-react';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { SearchableSelect } from '@/components/SearchableSelect';

type AssignedUser = {
    user_id: string;
    name: string;
    email: string;
    project_role: string;
};

type Props = {
    project: {
        id: string;
        name: string;
        name_ar: string | null;
        code: string;
        description: string | null;
        location: string | null;
        client_name: string | null;
        status: string;
        start_date: string | null;
        end_date: string | null;
    };
    assignedUsers: AssignedUser[];
    availableUsers: Array<{ id: string; name: string; email: string }>;
};

const PROJECT_ROLES = [
    { value: 'manager', label: 'Manager' },
    { value: 'engineer', label: 'Engineer' },
    { value: 'evaluator', label: 'Evaluator' },
    { value: 'viewer', label: 'Viewer' },
];

export default function Form({ project, assignedUsers, availableUsers }: Props) {
    const form = useForm({
        name: project.name,
        name_ar: project.name_ar ?? '',
        code: project.code,
        description: project.description ?? '',
        location: project.location ?? '',
        client_name: project.client_name ?? '',
        status: project.status,
        start_date: project.start_date ?? '',
        end_date: project.end_date ?? '',
    });

    const [newUserId, setNewUserId] = useState('');
    const [newUserRole, setNewUserRole] = useState('viewer');

    const submitProject: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(`/admin/projects/${project.id}`);
    };

    function assignUser() {
        if (!newUserId) return;
        router.post(
            `/admin/projects/${project.id}/assign-users`,
            { user_id: newUserId, project_role: newUserRole },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewUserId('');
                    setNewUserRole('viewer');
                },
            },
        );
    }

    function removeUser(userId: string) {
        router.delete(`/admin/projects/${project.id}/users/${userId}`, {
            preserveScroll: true,
        });
    }

    function updateUserRole(userId: string, role: string) {
        router.put(
            `/admin/projects/${project.id}/users/${userId}`,
            { project_role: role },
            { preserveScroll: true },
        );
    }

    const userOptions = availableUsers.map((u) => ({
        value: u.id,
        label: `${u.name} (${u.email})`,
    }));

    return (
        <>
            <Head title={`Edit Project - ${project.name}`} />

            <Heading
                title="Edit Project"
                description={`${project.code} - ${project.name}`}
            />

            <div className="mt-6 space-y-8 max-w-4xl">
                {/* Section 1: Project Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>Project Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitProject} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                                    <Label htmlFor="name_ar">Name (Arabic)</Label>
                                    <Input
                                        id="name_ar"
                                        dir="rtl"
                                        value={form.data.name_ar}
                                        onChange={(e) => form.setData('name_ar', e.target.value)}
                                    />
                                    {form.errors.name_ar && (
                                        <p className="text-sm text-destructive">{form.errors.name_ar}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="code">Code</Label>
                                    <Input
                                        id="code"
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                    />
                                    {form.errors.code && (
                                        <p className="text-sm text-destructive">{form.errors.code}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="status">Status</Label>
                                    <Select
                                        value={form.data.status}
                                        onValueChange={(value) => form.setData('status', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="draft">Draft</SelectItem>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="on_hold">On Hold</SelectItem>
                                            <SelectItem value="completed">Completed</SelectItem>
                                            <SelectItem value="cancelled">Cancelled</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {form.errors.status && (
                                        <p className="text-sm text-destructive">{form.errors.status}</p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Input
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                />
                                {form.errors.description && (
                                    <p className="text-sm text-destructive">{form.errors.description}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="location">Location</Label>
                                    <Input
                                        id="location"
                                        value={form.data.location}
                                        onChange={(e) => form.setData('location', e.target.value)}
                                    />
                                    {form.errors.location && (
                                        <p className="text-sm text-destructive">{form.errors.location}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="client_name">Client Name</Label>
                                    <Input
                                        id="client_name"
                                        value={form.data.client_name}
                                        onChange={(e) => form.setData('client_name', e.target.value)}
                                    />
                                    {form.errors.client_name && (
                                        <p className="text-sm text-destructive">{form.errors.client_name}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="start_date">Start Date</Label>
                                    <Input
                                        id="start_date"
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(e) => form.setData('start_date', e.target.value)}
                                    />
                                    {form.errors.start_date && (
                                        <p className="text-sm text-destructive">{form.errors.start_date}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="end_date">End Date</Label>
                                    <Input
                                        id="end_date"
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(e) => form.setData('end_date', e.target.value)}
                                    />
                                    {form.errors.end_date && (
                                        <p className="text-sm text-destructive">{form.errors.end_date}</p>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end pt-4">
                                <Button type="submit" disabled={form.processing}>
                                    Update Project
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Section 2: User Assignments */}
                <Card>
                    <CardHeader>
                        <CardTitle>Team Members</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {/* Add user row */}
                        <div className="mb-6 flex items-end gap-4">
                            <div className="flex-1 space-y-2">
                                <Label>User</Label>
                                <SearchableSelect
                                    options={userOptions}
                                    value={newUserId}
                                    onChange={setNewUserId}
                                    placeholder="Select a user..."
                                />
                            </div>
                            <div className="w-40 space-y-2">
                                <Label>Role</Label>
                                <Select value={newUserRole} onValueChange={setNewUserRole}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PROJECT_ROLES.map((role) => (
                                            <SelectItem key={role.value} value={role.value}>
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="button" onClick={assignUser} disabled={!newUserId}>
                                <UserPlus className="mr-2 h-4 w-4" />
                                Add
                            </Button>
                        </div>

                        {/* Assigned users list */}
                        {assignedUsers.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No users assigned to this project.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {assignedUsers.map((user) => (
                                    <div
                                        key={user.user_id}
                                        className="flex items-center justify-between rounded-md border p-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">{user.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {user.email}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Select
                                                value={user.project_role}
                                                onValueChange={(value) =>
                                                    updateUserRole(user.user_id, value)
                                                }
                                            >
                                                <SelectTrigger className="w-36">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {PROJECT_ROLES.map((role) => (
                                                        <SelectItem key={role.value} value={role.value}>
                                                            {role.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-destructive"
                                                onClick={() => removeUser(user.user_id)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
