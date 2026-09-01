import { useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    open: boolean;
    onClose: () => void;
    /** From the ProjectStatus enum, so this picker cannot drift from validation. */
    statusOptions: Array<{ value: string; labelKey: string }>;
};

/**
 * Create a project without leaving the list.
 *
 * Lives in its own file rather than inside Index so the list page stays about
 * the list; the two share nothing but the status options.
 */
export function CreateProjectDialog({ open, onClose, statusOptions }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        name_ar: '',
        code: '',
        description: '',
        location: '',
        client_name: '',
        status: statusOptions[0]?.value ?? 'active',
        start_date: '',
        end_date: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post('/admin/projects', {
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const errorEntries = Object.entries(form.errors).filter(([, message]) => Boolean(message));

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('pages.admin.new_project')}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    {errorEntries.length > 0 && (
                        <div
                            role="alert"
                            className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                        >
                            <p className="font-medium">{t('form.please_correct')}</p>
                            <ul className="mt-1 list-inside list-disc">
                                {errorEntries.map(([field, message]) => (
                                    <li key={field}>{message}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="create-name">{t('form.name')}</Label>
                            <Input
                                id="create-name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            {form.errors.name && (
                                <p className="text-sm text-destructive">{form.errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-name_ar">{t('form.name_arabic')}</Label>
                            <Input
                                id="create-name_ar"
                                dir="rtl"
                                value={form.data.name_ar}
                                onChange={(e) => form.setData('name_ar', e.target.value)}
                                aria-invalid={Boolean(form.errors.name_ar)}
                            />
                            {form.errors.name_ar && (
                                <p className="text-sm text-destructive">{form.errors.name_ar}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-code">{t('form.code')}</Label>
                            <Input
                                id="create-code"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                                aria-invalid={Boolean(form.errors.code)}
                            />
                            {form.errors.code && (
                                <p className="text-sm text-destructive">{form.errors.code}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-status">{t('form.status')}</Label>
                            <Select
                                value={form.data.status}
                                onValueChange={(value) => form.setData('status', value)}
                            >
                                <SelectTrigger id="create-status">
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
                            {form.errors.status && (
                                <p className="text-sm text-destructive">{form.errors.status}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-location">{t('form.location')}</Label>
                            <Input
                                id="create-location"
                                value={form.data.location}
                                onChange={(e) => form.setData('location', e.target.value)}
                            />
                            {form.errors.location && (
                                <p className="text-sm text-destructive">{form.errors.location}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-client_name">{t('form.client_name')}</Label>
                            <Input
                                id="create-client_name"
                                value={form.data.client_name}
                                onChange={(e) => form.setData('client_name', e.target.value)}
                            />
                            {form.errors.client_name && (
                                <p className="text-sm text-destructive">
                                    {form.errors.client_name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-start_date">{t('form.start_date')}</Label>
                            <Input
                                id="create-start_date"
                                type="date"
                                value={form.data.start_date}
                                onChange={(e) => form.setData('start_date', e.target.value)}
                            />
                            {form.errors.start_date && (
                                <p className="text-sm text-destructive">{form.errors.start_date}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-end_date">{t('form.end_date')}</Label>
                            <Input
                                id="create-end_date"
                                type="date"
                                value={form.data.end_date}
                                onChange={(e) => form.setData('end_date', e.target.value)}
                            />
                            {form.errors.end_date && (
                                <p className="text-sm text-destructive">{form.errors.end_date}</p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="create-description">{t('form.description')}</Label>
                        {/* A textarea, not an input: descriptions here run to a
                            paragraph and a single line hid all but the end of it. */}
                        <Textarea
                            id="create-description"
                            rows={3}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                        {form.errors.description && (
                            <p className="text-sm text-destructive">{form.errors.description}</p>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('btn.cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {t('btn.create_project')}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
