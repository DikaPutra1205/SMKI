import { cn } from '@/lib/utils';

interface CardProps {
    className?: string;
    children: React.ReactNode;
}

export function Card({ className, children }: CardProps) {
    return (
        <section className={cn('rounded-[14px] border border-border dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm', className)}>
            {children}
        </section>
    );
}

interface CardHeaderProps {
    title?: React.ReactNode;
    subtitle?: React.ReactNode;
    actions?: React.ReactNode;
    className?: string;
}

export function CardHeader({ title, subtitle, actions, className }: CardHeaderProps) {
    return (
        <div className={cn('flex items-start justify-between gap-3 border-b border-border dark:border-slate-700 px-5 py-4', className)}>
            <div>
                {title && <h3 className="text-[15px] font-bold text-navy dark:text-white">{title}</h3>}
                {subtitle && <p className="mt-0.5 text-xs text-muted dark:text-slate-400">{subtitle}</p>}
            </div>
            {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
    );
}

interface CardBodyProps {
    className?: string;
    children: React.ReactNode;
}

export function CardBody({ className, children }: CardBodyProps) {
    return <div className={cn('px-5 py-4', className)}>{children}</div>;
}