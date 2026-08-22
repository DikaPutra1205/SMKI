import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface NavChild {
    label: string;
    url: string;
    roles?: string[];
}

export interface NavEntry {
    label: string;
    url?: string;
    icon?: string;
    roles?: string[];
    children?: NavChild[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    navigation: NavEntry[];
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role?: string;
    unit_id?: number | null;
    unit?: { id: number; nama: string } | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}
