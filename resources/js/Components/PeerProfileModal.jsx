import Modal from '@/Components/Modal';
import UserAvatar from '@/Components/ui/UserAvatar';
import StatusBadge from '@/Components/ui/StatusBadge';
import { X } from 'lucide-react';

const roleLabels = {
    CASE_MANAGER: 'Case Manager',
    AGENCY: 'Agency',
    ADMIN: 'Admin',
};

export default function PeerProfileModal({ user, show, onClose }) {
    if (!user) return null;

    const emergency = user.emergency_contact || {};

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="relative p-5">
                {/* Close button */}
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute right-4 top-4 rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                    aria-label="Close"
                >
                    <X className="h-4 w-4" />
                </button>

                {/* 1. Profile Header */}
                <div className="flex items-start gap-4 pr-8">
                    <UserAvatar user={user} size="lg" />
                    <div className="min-w-0 flex-1 pt-1">
                        <p className="text-base font-bold text-slate-900 truncate">{user.name}</p>
                        <p className="text-sm text-slate-500 truncate">{user.email}</p>
                        <div className="mt-1.5 flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center rounded-[3px] border border-blue-200 bg-blue-50 px-2 py-[3px] text-[10px] font-bold uppercase tracking-wide text-blue-700">
                                {roleLabels[user.role] || user.role}
                            </span>
                            <StatusBadge status={user.is_active ? 'ACTIVE' : 'INACTIVE'} />
                        </div>
                    </div>
                </div>

                {/* Divider */}
                <div className="my-4 border-t border-slate-200" />

                {/* 2. Agency Information */}
                {user.agency && (
                    <div className="mb-4">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Agency</p>
                        <div className="flex items-start gap-3">
                            {user.agency.logo_url && (
                                <img
                                    src={user.agency.logo_url}
                                    alt={`${user.agency.name} logo`}
                                    className="h-10 w-10 shrink-0 rounded-lg border border-slate-200 bg-white object-contain p-1"
                                />
                            )}
                            <div>
                                <p className="text-sm font-semibold text-slate-800">{user.agency.name}</p>
                                {user.agency.description && (
                                    <p className="mt-0.5 text-xs leading-5 text-slate-500">{user.agency.description}</p>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* 3. Personal Information */}
                <div className="mb-4">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Personal Information</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Position</p>
                            <p className="text-sm font-semibold text-slate-900 mt-0.5">{user.position || '\u2014'}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Department</p>
                            <p className="text-sm font-semibold text-slate-900 mt-0.5">{user.department || '\u2014'}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Office Location</p>
                            <p className="text-sm font-semibold text-slate-900 mt-0.5">{user.office_location || '\u2014'}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Timezone</p>
                            <p className="text-sm font-semibold text-slate-900 mt-0.5">{user.timezone || '\u2014'}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Contact Number</p>
                            <p className="text-sm font-semibold text-slate-900 mt-0.5">{user.contact_number || '\u2014'}</p>
                        </div>
                        {user.bio && (
                            <div className="sm:col-span-2">
                                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Bio</p>
                                <p className="text-sm text-slate-700 mt-0.5">{user.bio}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* 4. Emergency Contact */}
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Emergency Contact</p>
                    {emergency.name ? (
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Name</p>
                                <p className="text-sm font-semibold text-slate-900 mt-0.5">{emergency.name}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Relation</p>
                                <p className="text-sm font-semibold text-slate-900 mt-0.5">{emergency.relation || '\u2014'}</p>
                            </div>
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Phone</p>
                                <p className="text-sm font-semibold text-slate-900 mt-0.5">{emergency.phone || '\u2014'}</p>
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-slate-500">&mdash;</p>
                    )}
                </div>
            </div>
        </Modal>
    );
}
