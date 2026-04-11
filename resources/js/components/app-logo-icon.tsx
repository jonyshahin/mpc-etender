import type { ImgHTMLAttributes } from 'react';

type AppLogoIconProps = Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'alt'>;

export default function AppLogoIcon({ className, ...props }: AppLogoIconProps) {
    return (
        <img
            src="/mpc-logo.png"
            alt="MPC Group"
            className={className}
            {...props}
        />
    );
}
