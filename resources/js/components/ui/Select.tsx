import { Check, ChevronDown } from 'lucide-react';
import React, { Children, forwardRef, Fragment, isValidElement } from 'react';
import { Listbox, Transition } from '@headlessui/react';
import { cn } from '@/lib/utils';

type SelectProps = React.SelectHTMLAttributes<HTMLSelectElement> & {
    label?: string;
    error?: string;
};

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
    ({ className, label, error, id, children, value, onChange, disabled, name, ...props }, ref) => {
        const selectId = id || (label ? `select-${label.toLowerCase().replace(/\s+/g, '-')}` : undefined);

        // Parse native option children into a data array for Headless UI Listbox
        const options = Children.toArray(children)
            .map((child) => {
                if (isValidElement(child) && child.type === 'option') {
                    return {
                        value: child.props.value,
                        label: child.props.children,
                        disabled: child.props.disabled,
                    };
                }
                return null;
            })
            .filter(Boolean) as { value: any; label: React.ReactNode; disabled?: boolean }[];

        const selectedOption = options.find((opt) => String(opt.value) === String(value)) || options[0];

        const handleValueChange = (newVal: any) => {
            if (onChange) {
                // Mock native event object so existing code using e.target.value continues to work
                const e = {
                    target: { value: newVal, name },
                    currentTarget: { value: newVal, name },
                } as any;
                onChange(e);
            }
        };

        return (
            <div className="flex flex-col gap-1.5">
                {label && (
                    <label htmlFor={selectId} className="text-xs font-semibold text-navy dark:text-white">
                        {label}
                    </label>
                )}
                
                <Listbox value={value} onChange={handleValueChange} disabled={disabled}>
                    <div className="relative">
                        <Listbox.Button
                            className={cn(
                                'relative w-full appearance-none rounded-[10px] border bg-white dark:bg-slate-900 h-10 px-3 pr-9 text-left text-sm text-ink dark:text-white shadow-sm',
                                'focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none',
                                error ? 'border-danger dark:border-red-700' : 'border-border-strong dark:border-slate-600',
                                disabled && 'opacity-60 cursor-not-allowed',
                                className
                            )}
                        >
                            <span className="block truncate">{selectedOption?.label || 'Select...'}</span>
                            <span className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                <ChevronDown className="h-4 w-4 text-muted dark:text-slate-400" aria-hidden="true" />
                            </span>
                        </Listbox.Button>
                        <Transition
                            as={Fragment}
                            leave="transition ease-in duration-100"
                            leaveFrom="opacity-100"
                            leaveTo="opacity-0"
                        >
                            <Listbox.Options className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white dark:bg-slate-800 py-1 text-base shadow-lg ring-1 ring-black/5 dark:ring-white/10 focus:outline-none sm:text-sm">
                                {options.map((option, optionIdx) => (
                                    <Listbox.Option
                                        key={optionIdx}
                                        className={({ active }) =>
                                            cn(
                                                'relative cursor-pointer select-none py-2.5 pl-10 pr-4',
                                                active ? 'bg-primary-50 text-primary-700 dark:bg-[#0a3b63]/60 dark:text-primary-300' : 'text-slate-900 dark:text-slate-200'
                                            )
                                        }
                                        value={option.value}
                                        disabled={option.disabled}
                                    >
                                        {({ selected, active }) => (
                                            <>
                                                <span className={cn('block truncate', selected ? 'font-semibold' : 'font-normal')}>
                                                    {option.label}
                                                </span>
                                                {selected ? (
                                                    <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-primary-600 dark:text-primary-400">
                                                        <Check className="h-4 w-4" aria-hidden="true" />
                                                    </span>
                                                ) : null}
                                            </>
                                        )}
                                    </Listbox.Option>
                                ))}
                            </Listbox.Options>
                        </Transition>
                    </div>
                </Listbox>
                {error && <span className="text-[11px] font-medium text-danger dark:text-red-400">{error}</span>}

                {/* Hidden select for actual form submission if they rely on it */}
                <select ref={ref} id={selectId} name={name} value={value} className="hidden" disabled={disabled} readOnly>
                    {children}
                </select>
            </div>
        );
    },
);

Select.displayName = 'Select';