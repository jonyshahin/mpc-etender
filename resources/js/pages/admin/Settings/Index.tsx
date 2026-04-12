import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Save } from 'lucide-react';

type Setting = {
    id: string;
    key: string;
    value: string | null;
    group: string;
    type: string;
    description: string | null;
};

type Props = {
    settingGroups: Record<string, Setting[]>;
};

const GROUP_ORDER = ['General', 'Notifications', 'Approvals', 'Security'];

export default function Index({ settingGroups }: Props) {
    const { t } = useTranslation();
    const allSettings = Object.values(settingGroups).flat();

    const form = useForm({
        settings: allSettings.map((s) => ({
            id: s.id,
            key: s.key,
            value: s.value ?? '',
        })),
    });

    function getSettingValue(id: string): string {
        const setting = form.data.settings.find((s) => s.id === id);
        return setting?.value ?? '';
    }

    function updateSetting(id: string, value: string) {
        form.setData(
            'settings',
            form.data.settings.map((s) => (s.id === id ? { ...s, value } : s)),
        );
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put('/admin/settings');
    }

    const sortedGroups = Object.keys(settingGroups).sort((a, b) => {
        const ai = GROUP_ORDER.indexOf(a);
        const bi = GROUP_ORDER.indexOf(b);
        return (ai === -1 ? 999 : ai) - (bi === -1 ? 999 : bi);
    });

    function renderSettingInput(setting: Setting) {
        const value = getSettingValue(setting.id);

        switch (setting.type) {
            case 'boolean':
                return (
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id={setting.key}
                            checked={value === '1' || value === 'true'}
                            onCheckedChange={(checked) =>
                                updateSetting(setting.id, checked ? '1' : '0')
                            }
                        />
                        <Label htmlFor={setting.key} className="font-normal">
                            {setting.description ?? setting.key}
                        </Label>
                    </div>
                );
            case 'integer':
                return (
                    <div className="space-y-2">
                        <Label htmlFor={setting.key}>{setting.key}</Label>
                        <Input
                            id={setting.key}
                            type="number"
                            value={value}
                            onChange={(e) => updateSetting(setting.id, e.target.value)}
                        />
                        {setting.description && (
                            <p className="text-xs text-muted-foreground">{setting.description}</p>
                        )}
                    </div>
                );
            case 'json':
                return (
                    <div className="space-y-2">
                        <Label htmlFor={setting.key}>{setting.key}</Label>
                        <textarea
                            id={setting.key}
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            value={value}
                            onChange={(e) => updateSetting(setting.id, e.target.value)}
                        />
                        {setting.description && (
                            <p className="text-xs text-muted-foreground">{setting.description}</p>
                        )}
                    </div>
                );
            default:
                return (
                    <div className="space-y-2">
                        <Label htmlFor={setting.key}>{setting.key}</Label>
                        <Input
                            id={setting.key}
                            value={value}
                            onChange={(e) => updateSetting(setting.id, e.target.value)}
                        />
                        {setting.description && (
                            <p className="text-xs text-muted-foreground">{setting.description}</p>
                        )}
                    </div>
                );
        }
    }

    return (
        <>
            <Head title="Settings" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('pages.admin.system_settings')}
                        description={t('pages.admin.system_settings_description')}
                    />
                    <Button onClick={handleSubmit} disabled={form.processing}>
                        <Save className="mr-2 h-4 w-4" />
                        {t('btn.save_all_settings')}
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {sortedGroups.map((group) => (
                        <Card key={group}>
                            <CardHeader>
                                <CardTitle>{group}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {settingGroups[group].map((setting) => (
                                    <div key={setting.id}>{renderSettingInput(setting)}</div>
                                ))}
                            </CardContent>
                        </Card>
                    ))}

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {t('btn.save_all_settings')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
