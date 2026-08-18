import { cn } from '@/lib/utils';

interface AvatarProps {
    name?: string;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}

const sizeClasses: Record<NonNullable<AvatarProps['size']>, string> = {
    sm: 'h-8 w-8 text-[11px]',
    md: 'h-9 w-9 text-xs',
    lg: 'h-10 w-10 text-sm',
};

export function Avatar({ name = '', size = 'md', className }: AvatarProps) {
    const initials = name
        .split(' ')
        .map((n) => n[0])
        .filter(Boolean)
        .join('')
        .substring(0, 2)
        .toUpperCase();

    return (
        <div
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full bg-primary font-bold text-white',
                sizeClasses[size],
                className,
            )}
        >
            {initials || '?'}
        </div>
    );
}