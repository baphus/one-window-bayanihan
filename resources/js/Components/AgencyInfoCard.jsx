import { usePage } from '@inertiajs/react';
import { CardSection, MetaTile, SubsectionCard } from '@/Components/ui/CardSection';

export default function AgencyInfoCard() {
    const user = usePage().props.auth.user;
    const agency = user?.agency;

    if (!agency) {
        return (
            <CardSection title="Agency">
                <p className="text-sm text-slate-500">No agency assigned.</p>
            </CardSection>
        );
    }

    return (
        <CardSection title="Agency">
            <div className="space-y-4">
                <div className="flex items-center gap-3">
                    {agency.logo_url ? (
                        <img src={agency.logo_url} alt={agency.name} className="h-10 w-10 rounded-full object-contain border border-slate-200" />
                    ) : (
                        <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-sm font-bold text-blue-700">
                            {agency.name?.charAt(0)?.toUpperCase() || 'A'}
                        </div>
                    )}
                    <div>
                        <p className="text-sm font-semibold text-slate-900">{agency.name}</p>
                        {agency.short && <p className="text-[11px] text-slate-500">{agency.short}</p>}
                    </div>
                </div>

                {agency.description && (
                    <p className="text-[13px] text-slate-600 leading-relaxed">{agency.description}</p>
                )}

                <div className="grid grid-cols-2 gap-2">
                    {agency.contact_info && (
                        <MetaTile label="Contact Info" value={agency.contact_info} />
                    )}
                    {user.position && (
                        <MetaTile label="Your Position" value={user.position} />
                    )}
                    {user.office_location && (
                        <MetaTile label="Office Location" value={user.office_location} />
                    )}
                    {user.department && (
                        <MetaTile label="Department" value={user.department} />
                    )}
                </div>

                {agency.services?.length > 0 && (
                    <div className="pt-1">
                        <SubsectionCard title="Services Offered">
                            <div className="flex flex-wrap gap-1.5">
                                {agency.services.map((svc) => (
                                    <span
                                        key={svc.id}
                                        className="inline-block rounded-full bg-blue-50 border border-blue-100 px-2.5 py-0.5 text-[10px] font-semibold text-blue-700"
                                    >
                                        {svc.name}
                                    </span>
                                ))}
                            </div>
                        </SubsectionCard>
                    </div>
                )}
            </div>
        </CardSection>
    );
}
