/**
 * The upload cap, read from the server rather than restated here.
 *
 * POLICY-01 lives in App\Rules\PdfFile; app.blade.php injects it as
 * `window.__maxUploadBytes__`. Hardcoding the number client-side is what let
 * the browser-side check and the server rule drift apart in the first place —
 * a file the page accepted could still be rejected on submit.
 */

/** Matches PdfFile::MAX_KB, used only if the injection is missing. */
const FALLBACK_BYTES = 102400 * 1024;

export function maxUploadBytes(): number {
    const injected = (window as unknown as { __maxUploadBytes__?: number }).__maxUploadBytes__;

    return typeof injected === 'number' && injected > 0 ? injected : FALLBACK_BYTES;
}

/** Human-readable cap for hints and errors, e.g. `100 MB`. Mirrors PdfFile::maxLabel(). */
export function maxUploadLabel(): string {
    const mb = maxUploadBytes() / (1024 * 1024);

    return `${Number.isInteger(mb) ? mb : mb.toFixed(1)} MB`;
}
