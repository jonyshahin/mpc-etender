import { Head, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { Badge } from '@/components/ui/badge';
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

const GROUP_ORDER = ['general', 'display', 'notifications', 'approvals', 'security'];

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

    useEffect(() => {
        if (import.meta.env.DEV) {
            allSettings.forEach((s) => {
                const labelKey = `settings.${s.key}.label`;
                if (t(labelKey) === labelKey) {
                    console.warn(`[i18n] Missing translation key: ${labelKey}`);
                }
            });
        }
    }, [allSettings, t]);

    function settingLabel(key: string): string {
        return t(`settings.${key}.label`);
    }

    function settingDescription(key: string, fallback: string | null): string | null {
        const tKey = `settings.${key}.description`;
        const translated = t(tKey);
        return translated === tKey ? fallback : translated;
    }

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

        // BUG-28 interim mitigation: the "2FA mandatory for internal users"
        // toggle persists to system_settings but no enforcement code reads it
        // (verdict COSMETIC, investigation commit 218f6ac). Forcing the UI to
        // disabled + unchecked + "Coming soon" badge prevents the false-
        // security signal until the full enforcement layer ships (~5-7d).
        // Persisted DB value is left untouched — flipping it from here would
        // be a behavior change masquerading as a UI fix. When the full
        // enforcement build lands, delete this whole branch.
        if (setting.key === 'security.2fa_mandatory_internal') {
            return (
                <div className="space-y-1.5">
                    <div className="flex items-center gap-3">
                        <Checkbox id={setting.key} checked={false} disabled />
                        <Label
                            htmlFor={setting.key}
                            className="font-normal text-muted-foreground"
                        >
                            {settingLabel(setting.key)}
                        </Label>
                        <Badge variant="secondary" className="ms-2">
                            {t('common.coming_soon')}
                        </Badge>
                    </div>
                    {settingDescription(setting.key, setting.description) && (
                        <p className="text-xs text-muted-foreground ms-7">
                            {settingDescription(setting.key, setting.description)}
                        </p>
                    )}
                </div>
            );
        }

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
                            {settingLabel(setting.key)}
                        </Label>
                    </div>
                );
            case 'integer':
                return (
                    <div className="space-y-2">
                        <Label htmlFor={setting.key}>{settingLabel(setting.key)}</Label>
                        <Input
                            id={setting.key}
                            type="number"
                            value={value}
                            onChange={(e) => updateSetting(setting.id, e.target.value)}
                        />
                        {settingDescription(setting.key, setting.description) && (
                            <p className="text-xs text-muted-foreground">
                                {settingDescription(setting.key, setting.description)}
                            </p>
                        )}
                    </div>
                );
            case 'json':
                return (
                    <div className="space-y-2">
                        <Label htmlFor={setting.key}>{settingLabel(setting.key)}</Label>
                        <textarea
                            id={setting.key}
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            value={value}
                            onChange={(e) => updateSetting(setting.id, e.target.value)}
                        />
                        {settingDescription(setting.key, setting.description) && (
                            <p className="text-xs text-muted-foreground">
                                {settingDescription(setting.key, setting.description)}
                            </p>
                        )}
                    </div>
                );
            default:
                return (
                    <div className="space-y-2">
                        <Label htmlFor={setting.key}>{settingLabel(setting.key)}</Label>
                        <Input
                            id={setting.key}
                            value={value}
                            onChange={(e) => updateSetting(setting.id, e.target.value)}
                        />
                        {settingDescription(setting.key, setting.description) && (
                            <p className="text-xs text-muted-foreground">
                                {settingDescription(setting.key, setting.description)}
                            </p>
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
                                <CardTitle>{t(`settings.group.${group}`)}</CardTitle>
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
