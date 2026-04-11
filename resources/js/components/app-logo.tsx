import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center overflow-hidden rounded-md bg-white">
                <AppLogoIcon className="size-8 object-contain" />
            </div>
            <div className="ml-2 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    MPC e-Tender
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Digital Procurement
                </span>
            </div>
        </>
    );
}
