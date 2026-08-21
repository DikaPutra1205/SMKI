import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-react';
import { forwardRef } from 'react';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                primary: 'bg-primary text-white shadow-blue hover:bg-primary-700',
                secondary: 'border border-border-strong dark:border-slate-600 bg-white dark:bg-slate-900 text-navy dark:text-white hover:bg-surface dark:hover:bg-slate-800',
                destructive: 'bg-danger text-white hover:bg-danger/90',
                ghost: 'text-muted dark:text-slate-400 hover:bg-surface dark:hover:bg-slate-800 hover:text-navy dark:hover:text-white',
                outline: 'border border-border dark:border-slate-700 bg-white dark:bg-slate-900 text-navy dark:text-white hover:bg-surface dark:hover:bg-slate-800',
                success: 'bg-success text-white hover:bg-success/90',
            },
            size: {
                sm: 'h-8 px-3 text-xs',
                md: 'h-10 px-4',
                lg: 'h-11 px-5',
                icon: 'h-9 w-9',
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'md',
        },
    },
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    asChild?: boolean;
    loading?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, asChild = false, loading = false, children, disabled, ...props }, ref) => {
        const Comp = asChild ? Slot : 'button';

        return (
            <Comp
                className={cn(buttonVariants({ variant, size, className }))}
                ref={ref}
                disabled={disabled || loading}
                {...props}
            >
                {loading && <Loader2 className="h-4 w-4 animate-spin" />}
                {children}
            </Comp>
        );
    },
);

Button.displayName = 'Button';

export { buttonVariants };