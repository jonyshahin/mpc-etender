import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/**
 * Dial codes offered in the picker. Iraq first — it is the default for this
 * deployment — then neighbours and the countries MPC vendors most often
 * register from, so the common cases need no scrolling.
 */
export const DIAL_CODES: Array<{ code: string; label: string }> = [
    { code: '+964', label: 'Iraq' },
    { code: '+90', label: 'Türkiye' },
    { code: '+98', label: 'Iran' },
    { code: '+963', label: 'Syria' },
    { code: '+962', label: 'Jordan' },
    { code: '+961', label: 'Lebanon' },
    { code: '+965', label: 'Kuwait' },
    { code: '+966', label: 'Saudi Arabia' },
    { code: '+971', label: 'United Arab Emirates' },
    { code: '+974', label: 'Qatar' },
    { code: '+973', label: 'Bahrain' },
    { code: '+968', label: 'Oman' },
    { code: '+20', label: 'Egypt' },
    { code: '+44', label: 'United Kingdom' },
    { code: '+1', label: 'United States / Canada' },
];

export const DEFAULT_DIAL_CODE = '+964';

/**
 * Split a stored E.164-ish number into dial code and national part.
 *
 * Matches the longest dial code first: '+964' and '+96' would both prefix-match
 * an Iraqi number, and picking the shorter one would silently move a digit into
 * the national part.
 */
export function splitPhone(value: string): { dial: string; national: string } {
    // '00' is the international access prefix used across the region; treat it as
    // an alias for '+' so a pasted 00964… number is understood.
    const trimmed = (value ?? '')
        .replace(/[\s\-()]/g, '')
        .replace(/^00/, '+');

    const match = [...DIAL_CODES]
        .sort((a, b) => b.code.length - a.code.length)
        .find((c) => trimmed.startsWith(c.code));

    if (match) {
        return { dial: match.code, national: trimmed.slice(match.code.length) };
    }

    // Unrecognised country, or a legacy number stored without one.
    return { dial: DEFAULT_DIAL_CODE, national: trimmed.replace(/^\+/, '') };
}

/**
 * Drop the national trunk prefix.
 *
 * Iraqi mobiles are written '0750…' locally but '+964750…' internationally, and
 * the same holds across the region. Without this, an admin typing the number the
 * way it appears on a business card would store '+9640750…' — a wrong number that
 * still passes validation.
 */
function stripTrunkPrefix(digits: string): string {
    return digits.replace(/^0+/, '');
}

type Props = {
    id: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
};

/**
 * Phone entry as a country-code select plus a national number, storing a single
 * combined string (e.g. "+9647501234567") in the form field.
 */
export function PhoneField({ id, value, onChange, placeholder }: Props) {
    const parsed = useMemo(() => splitPhone(value), [value]);

    // The picked country has to live here as well as in the parent's string.
    // emit() returns '' while the number box is empty, so a Select bound purely
    // to the parent value would snap back to the default the instant a country
    // was chosen — and country-then-number is the natural entry order, so a
    // +971 number would silently be stored as +964.
    const [pickedDial, setPickedDial] = useState(parsed.dial);

    // A stored number is authoritative; the local pick only fills the gap while
    // the field is empty. Derived, not an effect, so there is no sync loop.
    const dial = value.trim() === '' ? pickedDial : parsed.dial;

    const emit = (nextDial: string, nextNational: string) => {
        const digits = stripTrunkPrefix(nextNational.replace(/[^\d]/g, ''));

        // Emit '' rather than a bare dial code so `nullable` fields stay empty
        // instead of persisting a meaningless "+964".
        onChange(digits === '' ? '' : `${nextDial}${digits}`);
    };

    const changeDial = (next: string) => {
        setPickedDial(next);
        emit(next, parsed.national);
    };

    return (
        <div className="flex gap-2">
            <Select value={dial} onValueChange={changeDial}>
                <SelectTrigger id={`${id}_dial`} className="w-[132px] shrink-0">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {DIAL_CODES.map((country) => (
                        <SelectItem key={country.code} value={country.code}>
                            {country.code} {country.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Input
                id={id}
                type="tel"
                inputMode="tel"
                dir="ltr"
                value={parsed.national}
                placeholder={placeholder}
                onChange={(e) => emit(dial, e.target.value)}
            />
        </div>
    );
}
