import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type EngineCriteria = {
    weight_price: number;
    weight_technical: number;
    weight_timeline: number;
    weight_references: number;
    weight_payment_flexibility: number;
    gate_bank_guarantee: boolean;
    gate_mandatory_documents: boolean;
    margin_below: number;
    contract_type: string | null;
    contract_type_label: string | null;
    timeline_formula: string | null;
};

export type EngineOptions = {
    contractTypes: Array<{
        value: string;
        labelKey: string;
        timelineFormula: string | null;
    }>;
    timelineFormulas: Array<{
        value: string;
        labelKey: string;
        explanationKey: string;
    }>;
};

export const ENGINE_WEIGHTS = [
    ['price', 'weight_price'],
    ['technical', 'weight_technical'],
    ['timeline', 'weight_timeline'],
    ['references', 'weight_references'],
    ['payment_flexibility', 'weight_payment_flexibility'],
] as const;

type Props = {
    value: EngineCriteria;
    onChange: (patch: Partial<EngineCriteria>) => void;
    options: EngineOptions;
    errors?: Record<string, string>;
    /** Prefix of the server's error keys — `engine_profile.` in the wizard. */
    errorPrefix?: string;
    disabled?: boolean;
};

/**
 * A tender's engine scoring criteria, as edited while the tender is a draft.
 *
 * Contract type picks the timeline formula. The two seeded types fix it and
 * the field only explains it; anything else makes the officer choose, with
 * both formulas' effect on short durations in front of them, so the choice is
 * deliberate rather than inherited (INTEGRATION-DESIGN §1.4).
 */
export function EngineCriteriaFields({
    value,
    onChange,
    options,
    errors = {},
    errorPrefix = '',
    disabled = false,
}: Props) {
    const { t } = useTranslation();
    const total = ENGINE_WEIGHTS.reduce(
        (sum, [, field]) => sum + (Number(value[field]) || 0),
        0,
    );
    const contractType =
        options.contractTypes.find((c) => c.value === value.contract_type) ??
        null;
    const fixedFormula = contractType?.timelineFormula
        ? (options.timelineFormulas.find(
              (f) => f.value === contractType.timelineFormula,
          ) ?? null)
        : null;
    const error = (field: string) => errors[`${errorPrefix}${field}`];

    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="engine-contract-type">
                        {t('engine.criteria.contract_type')}
                    </Label>
                    <select
                        id="engine-contract-type"
                        value={value.contract_type ?? ''}
                        onChange={(event) => {
                            const next = event.target.value || null;

                            // A type that fixes its formula carries it; any
                            // other starts with none chosen. Keeping the old
                            // one when switching to "Other" would pre-select a
                            // formula the officer never actually picked.
                            onChange({
                                contract_type: next,
                                timeline_formula:
                                    options.contractTypes.find(
                                        (c) => c.value === next,
                                    )?.timelineFormula ?? null,
                            });
                        }}
                        disabled={disabled}
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs disabled:opacity-50"
                    >
                        <option value="">{t('engine.criteria.not_set')}</option>
                        {options.contractTypes.map((option) => (
                            <option key={option.value} value={option.value}>
                                {t(option.labelKey)}
                            </option>
                        ))}
                    </select>
                    {error('contract_type') ? (
                        <p className="text-xs text-red-600 dark:text-red-400">
                            {error('contract_type')}
                        </p>
                    ) : null}
                </div>

                {value.contract_type === 'other' ? (
                    <div className="space-y-2">
                        <Label htmlFor="engine-contract-type-label">
                            {t('engine.criteria.contract_type_label')}
                        </Label>
                        <Input
                            id="engine-contract-type-label"
                            value={value.contract_type_label ?? ''}
                            onChange={(event) =>
                                onChange({
                                    contract_type_label: event.target.value,
                                })
                            }
                            maxLength={100}
                            disabled={disabled}
                        />
                        {error('contract_type_label') ? (
                            <p className="text-xs text-red-600 dark:text-red-400">
                                {error('contract_type_label')}
                            </p>
                        ) : null}
                    </div>
                ) : null}
            </div>

            <fieldset className="space-y-2" disabled={disabled}>
                <legend className="text-sm font-medium">
                    {t('engine.criteria.timeline_formula')}
                </legend>
                {fixedFormula ? (
                    <p className="text-sm text-muted-foreground">
                        <span className="font-medium text-foreground">
                            {t(fixedFormula.labelKey)}
                        </span>{' '}
                        — {t('engine.criteria.timeline_formula_fixed')}{' '}
                        {t(fixedFormula.explanationKey)}
                    </p>
                ) : value.contract_type === 'other' ? (
                    <>
                        <p className="text-sm text-muted-foreground">
                            {t('engine.criteria.timeline_formula_choose')}
                        </p>
                        <div className="grid gap-2 md:grid-cols-2">
                            {options.timelineFormulas.map((formula) => (
                                <label
                                    key={formula.value}
                                    className={cn(
                                        'flex cursor-pointer gap-3 rounded-md border p-3 text-sm',
                                        value.timeline_formula ===
                                            formula.value &&
                                            'border-primary bg-primary/5',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="engine-timeline-formula"
                                        value={formula.value}
                                        checked={
                                            value.timeline_formula ===
                                            formula.value
                                        }
                                        onChange={() =>
                                            onChange({
                                                timeline_formula: formula.value,
                                            })
                                        }
                                        className="mt-0.5"
                                    />
                                    <span>
                                        <span className="block font-medium">
                                            {t(formula.labelKey)}
                                        </span>
                                        <span className="block text-muted-foreground">
                                            {t(formula.explanationKey)}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {t('engine.publish.no_contract_type')}
                    </p>
                )}
                {error('timeline_formula') ? (
                    <p className="text-xs text-red-600 dark:text-red-400">
                        {error('timeline_formula')}
                    </p>
                ) : null}
            </fieldset>

            <fieldset className="space-y-3" disabled={disabled}>
                <legend className="text-sm font-medium">
                    {t('engine.criteria.weights')}
                </legend>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {ENGINE_WEIGHTS.map(([key, field]) => (
                        <div key={field} className="space-y-1">
                            <Label
                                htmlFor={`engine-${field}`}
                                className="text-xs text-muted-foreground"
                            >
                                {t(`engine.criterion.${key}`)}
                            </Label>
                            <Input
                                id={`engine-${field}`}
                                type="number"
                                inputMode="numeric"
                                min={0}
                                max={100}
                                step={1}
                                value={value[field]}
                                onChange={(event) =>
                                    onChange({
                                        [field]:
                                            event.target.value === ''
                                                ? 0
                                                : Number(event.target.value),
                                    })
                                }
                                className="tabular-nums"
                            />
                        </div>
                    ))}
                </div>
                <p
                    className={cn(
                        'text-sm tabular-nums',
                        total === 100
                            ? 'text-muted-foreground'
                            : 'font-medium text-red-600 dark:text-red-400',
                    )}
                    aria-live="polite"
                >
                    <bdi>{t('engine.criteria.weights_total', { total })}</bdi>
                </p>
                {error('weight_price') ? (
                    <p className="text-xs text-red-600 dark:text-red-400">
                        {error('weight_price')}
                    </p>
                ) : null}
            </fieldset>

            <fieldset className="space-y-2" disabled={disabled}>
                <legend className="text-sm font-medium">
                    {t('engine.criteria.gates')}
                </legend>
                <p className="text-xs text-muted-foreground">
                    {t('engine.criteria.gates_hint')}
                </p>
                {(
                    ['gate_bank_guarantee', 'gate_mandatory_documents'] as const
                ).map((field) => (
                    <label
                        key={field}
                        className="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            checked={value[field]}
                            onCheckedChange={(checked) =>
                                onChange({ [field]: checked === true })
                            }
                        />
                        {t(`engine.gate.${field.replace('gate_', '')}`)}
                    </label>
                ))}
            </fieldset>

            <div className="max-w-xs space-y-1">
                <Label htmlFor="engine-margin-below" className="text-sm">
                    {t('engine.criteria.margin_below')}
                </Label>
                <Input
                    id="engine-margin-below"
                    type="number"
                    inputMode="decimal"
                    min={0}
                    max={100}
                    step="0.1"
                    value={value.margin_below}
                    onChange={(event) =>
                        onChange({
                            margin_below:
                                event.target.value === ''
                                    ? 0
                                    : Number(event.target.value),
                        })
                    }
                    disabled={disabled}
                    className="tabular-nums"
                />
                {error('margin_below') ? (
                    <p className="text-xs text-red-600 dark:text-red-400">
                        {error('margin_below')}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
