import { createClient, SupabaseClient } from '@supabase/supabase-js';

const supabaseUrl = import.meta.env.VITE_SUPABASE_URL as string | undefined;
const supabaseAnonKey = import.meta.env.VITE_SUPABASE_ANON_KEY as string | undefined;

export const isSupabaseConfigured = Boolean(supabaseUrl && supabaseAnonKey);

let client: SupabaseClient | null = null;

if (isSupabaseConfigured) {
    client = createClient(supabaseUrl!, supabaseAnonKey!, {
        auth: {
            persistSession: false,
            autoRefreshToken: false,
        },
        realtime: {
            params: {
                eventsPerSecond: 10,
            },
        },
    });
} else {
    if (import.meta.env.DEV) {
        console.info(
            '[Supabase] VITE_SUPABASE_URL atau VITE_SUPABASE_ANON_KEY belum diisi di .env. Notifikasi real-time akan menggunakan fallback polling.',
        );
    }
}

export const supabase = client;
