import { Eye, UserPlus, Pencil, Trash2, LogIn, LogOut } from 'lucide-react';

export const actionConfig = {
    CREATE: { icon: UserPlus, bg: 'bg-emerald-50', text: 'text-emerald-600', ring: 'ring-emerald-200', label: 'Created' },
    UPDATE: { icon: Pencil, bg: 'bg-blue-50', text: 'text-blue-600', ring: 'ring-blue-200', label: 'Updated' },
    DELETE: { icon: Trash2, bg: 'bg-rose-50', text: 'text-rose-600', ring: 'ring-rose-200', label: 'Removed' },
    VIEW: { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', ring: 'ring-slate-200', label: 'Viewed' },
    LOGIN: { icon: LogIn, bg: 'bg-indigo-50', text: 'text-indigo-600', ring: 'ring-indigo-200', label: 'Signed In' },
    LOGOUT: { icon: LogOut, bg: 'bg-orange-50', text: 'text-orange-600', ring: 'ring-orange-200', label: 'Signed Out' },
};
