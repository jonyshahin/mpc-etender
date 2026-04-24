import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
    FileText,
    Globe,
    Lock,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { LOCALES, LOCALE_BY_CODE, type LocaleCode } from '@/lib/locales';
import { dashboard, login } from '@/routes';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { t } = useTranslation();
    const page = usePage();
    const user = (page.props.auth as { user?: { name?: string } | null }).user;
    const locale = ((page.props as { locale?: string }).locale ?? 'en') as LocaleCode;
    const currentLabel = LOCALE_BY_CODE[locale]?.label ?? 'English';

    const switchLocale = (target: LocaleCode) => {
        if (target === locale) return;
        router.put(
            '/user/language',
            { language: target },
            { onSuccess: () => window.location.reload() },
        );
    };

    return (
        <>
            <Head title={t('welcome.head_title')}>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen text-foreground">
                {/* ── Header ── */}
                <header className="border-b border-border/50 bg-background">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-3">
                            <AppLogoIcon className="size-14 object-contain" />
                            <div>
                                <p className="text-base font-semibold leading-tight">
                                    {t('welcome.brand_title')}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('welcome.brand_subtitle')}
                                </p>
                            </div>
                        </div>

                        <nav className="flex items-center gap-3">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium transition hover:bg-accent"
                                    >
                                        <Globe className="size-4" />
                                        <span>{currentLabel}</span>
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {LOCALES.map((l) => (
                                        <DropdownMenuItem
                                            key={l.code}
                                            onSelect={() => switchLocale(l.code)}
                                            disabled={l.code === locale}
                                        >
                                            {l.label}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                            {user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                                >
                                    {t('welcome.cta_go_to_dashboard')}
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="inline-flex items-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium transition hover:bg-accent"
                                    >
                                        {t('welcome.staff_login')}
                                    </Link>
                                    <a
                                        href="/vendor/login"
                                        className="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                                    >
                                        {t('welcome.vendor_portal')}
                                    </a>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* ── Hero ── */}
                <section className="mx-auto max-w-6xl px-6 py-20">
                    <div className="grid items-center gap-10 md:grid-cols-[auto_1fr] md:gap-16">
                        <div className="flex justify-center md:justify-start">
                            <img
                                src="/mpc-logo.png"
                                alt="MPC Group"
                                className="w-40 md:w-80 lg:w-96"
                            />
                        </div>
                        <div className="flex flex-col items-center text-center md:items-start md:text-start">
                            <div className="inline-flex items-center gap-2 rounded-full border border-border/50 bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                                <span className="relative flex h-2 w-2">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75"></span>
                                    <span className="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                                </span>
                                {t('welcome.system_online')}
                            </div>

                            <h1 className="mt-6 text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
                                {t('welcome.hero_line_1')}
                                <br />
                                <span className="bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
                                    {t('welcome.hero_line_2')}
                                </span>
                            </h1>

                            <p className="mt-6 max-w-2xl text-base text-muted-foreground sm:text-lg">
                                {t('welcome.hero_subhead')}
                            </p>

                            <div className="mt-10 flex flex-wrap items-center justify-center gap-3 md:justify-start">
                                {!user && (
                                    <>
                                        <Link
                                            href={login()}
                                            className="inline-flex items-center gap-2 rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                                        >
                                            <Lock className="size-4" />
                                            {t('welcome.staff_login')}
                                        </Link>
                                        {canRegister && (
                                            <a
                                                href="/vendor/register"
                                                className="inline-flex items-center gap-2 rounded-md border border-input bg-background px-6 py-3 text-sm font-medium transition hover:bg-accent"
                                            >
                                                <Building2 className="size-4" />
                                                {t('welcome.cta_register_vendor')}
                                            </a>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                {/* ── Features grid ── */}
                <section className="mx-auto max-w-6xl px-6 pb-20">
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <FeatureCard
                            icon={<Building2 className="size-5" />}
                            title={t('welcome.card_prequal_title')}
                            description={t('welcome.card_prequal_desc')}
                        />
                        <FeatureCard
                            icon={<FileText className="size-5" />}
                            title={t('welcome.card_tender_title')}
                            description={t('welcome.card_tender_desc')}
                        />
                        <FeatureCard
                            icon={<Lock className="size-5" />}
                            title={t('welcome.card_sealed_title')}
                            description={t('welcome.card_sealed_desc')}
                        />
                        <FeatureCard
                            icon={<Users className="size-5" />}
                            title={t('welcome.card_committee_title')}
                            description={t('welcome.card_committee_desc')}
                        />
                        <FeatureCard
                            icon={<CheckCircle2 className="size-5" />}
                            title={t('welcome.card_approval_title')}
                            description={t('welcome.card_approval_desc')}
                        />
                        <FeatureCard
                            icon={<ShieldCheck className="size-5" />}
                            title={t('welcome.card_audit_title')}
                            description={t('welcome.card_audit_desc')}
                        />
                    </div>
                </section>

                {/* ── Footer ── */}
                <footer className="border-t border-border/50 bg-muted">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-6 py-6 text-xs text-muted-foreground sm:flex-row">
                        <p>
                            {t('welcome.footer_copyright', {
                                year: new Date().getFullYear(),
                            })}
                        </p>
                        <p>{t('welcome.footer_tagline')}</p>
                    </div>
                </footer>
            </div>
        </>
    );
}

function FeatureCard({
    icon,
    title,
    description,
}: {
    icon: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <div className="rounded-lg border border-[color:var(--brand-gold-soft)] bg-card p-6 shadow-sm transition hover:border-[color:var(--brand-gold)] hover:shadow-md">
            <div className="mb-4 flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                {icon}
            </div>
            <h3 className="mb-2 text-base font-semibold">{title}</h3>
            <p className="text-sm text-muted-foreground">{description}</p>
        </div>
    );
}
