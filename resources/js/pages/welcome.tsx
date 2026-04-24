import { Head, Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
    FileText,
    Lock,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login } from '@/routes';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;
    const user = (auth as { user?: { name?: string } | null }).user;

    return (
        <>
            <Head title="MPC e-Tender — Digital Procurement Platform">
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
                                <p className="text-base font-semibold leading-tight">MPC e-Tender</p>
                                <p className="text-xs text-muted-foreground">Digital Procurement Platform</p>
                            </div>
                        </div>

                        <nav className="flex items-center gap-3">
                            {user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                                >
                                    Go to Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="inline-flex items-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium transition hover:bg-accent"
                                    >
                                        Staff Login
                                    </Link>
                                    <a
                                        href="/vendor/login"
                                        className="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                                    >
                                        Vendor Portal
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
                                System Online
                            </div>

                            <h1 className="mt-6 text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
                                Tender management,
                                <br />
                                <span className="bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
                                    end to end.
                                </span>
                            </h1>

                            <p className="mt-6 max-w-2xl text-base text-muted-foreground sm:text-lg">
                                MPC Group's construction procurement platform — from vendor prequalification through
                                sealed bid submission, committee evaluation, multi-level approval, and award notification.
                            </p>

                            <div className="mt-10 flex flex-wrap items-center justify-center gap-3 md:justify-start">
                                {!user && (
                                    <>
                                        <Link
                                            href={login()}
                                            className="inline-flex items-center gap-2 rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                                        >
                                            <Lock className="size-4" />
                                            Staff Login
                                        </Link>
                                        {canRegister && (
                                            <a
                                                href="/vendor/register"
                                                className="inline-flex items-center gap-2 rounded-md border border-input bg-background px-6 py-3 text-sm font-medium transition hover:bg-accent"
                                            >
                                                <Building2 className="size-4" />
                                                Register as Vendor
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
                            title="Vendor Prequalification"
                            description="Document-backed vendor onboarding with admin review and category-based matching."
                        />
                        <FeatureCard
                            icon={<FileText className="size-5" />}
                            title="Tender Management"
                            description="Multi-step tender creation with BOQ builder, document versioning, addenda, and clarifications."
                        />
                        <FeatureCard
                            icon={<Lock className="size-5" />}
                            title="Sealed Bidding"
                            description="AES-256 encryption of bid pricing until opening date. Dual authorization for bid opening."
                        />
                        <FeatureCard
                            icon={<Users className="size-5" />}
                            title="Committee Evaluation"
                            description="Technical and financial committees score bids independently. Two-envelope support."
                        />
                        <FeatureCard
                            icon={<CheckCircle2 className="size-5" />}
                            title="Approval Workflows"
                            description="Value-based multi-level approval chains with delegation and auto-escalation."
                        />
                        <FeatureCard
                            icon={<ShieldCheck className="size-5" />}
                            title="Audit & Compliance"
                            description="Append-only audit trail, document access logs, and full bilingual (EN/AR) support."
                        />
                    </div>
                </section>

                {/* ── Footer ── */}
                <footer className="border-t border-border/50 bg-muted">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-6 py-6 text-xs text-muted-foreground sm:flex-row">
                        <p>© {new Date().getFullYear()} MPC Group. All rights reserved.</p>
                        <p>MPC e-Tender · Digital Procurement Platform</p>
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
