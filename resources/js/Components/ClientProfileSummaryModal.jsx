import Modal from '@/Components/Modal';
import { MetaTile } from '@/Components/ui/CardSection';
import { resolveAddressValue } from '@/lib/addressResolver';

function formatDate(value) {
    if (!value) return 'N/A';
    try {
        const d = new Date(value);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return 'N/A';
    }
}

export default function ClientProfileSummaryModal({
    show,
    onClose,
    onConfirm,
    client,
}) {
    if (!client) return null;

    const fullName = [client.first_name, client.middle_initial, client.last_name, client.suffix]
        .filter(Boolean)
        .join(' ');

    const address = client.addresses?.[0];
    const employment = client.employments?.[0];
    const kin = client.nextOfKin?.[0];

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            {/* Header */}
            <div className="p-6 pb-0">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-bold text-slate-900">Client Profile Summary</h2>
                        <p className="text-[13px] text-slate-500 mt-1">
                            Review client details before confirming.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                    >
                        <span className="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
            </div>

            {/* Content */}
            <div className="p-6 space-y-5">
                {/* Personal Information */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-3">
                        Personal Information
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <MetaTile label="Full Name" value={fullName} />
                        <MetaTile label="Sex" value={client.sex || 'N/A'} />
                        <MetaTile label="Date of Birth" value={client.date_of_birth || 'N/A'} />
                        <MetaTile label="Email" value={client.email || 'N/A'} />
                        <MetaTile label="Contact Number" value={client.contact_number || 'N/A'} />
                    </div>
                </div>

                {/* Address */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-3">
                        Address
                    </h3>
                    {address ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <MetaTile label="Region" value={resolveAddressValue(address.region_name || address.region) || 'N/A'} />
                            <MetaTile label="Province" value={resolveAddressValue(address.province_name || address.province) || 'N/A'} />
                            <MetaTile label="City/Municipality" value={resolveAddressValue(address.city_municipality_name || address.city_municipality) || 'N/A'} />
                            <MetaTile label="Barangay" value={resolveAddressValue(address.barangay_name || address.barangay) || 'N/A'} />
                            <MetaTile label="Street" value={address.street || 'N/A'} />
                        </div>
                    ) : (
                        <p className="text-[13px] italic text-slate-400">No address recorded</p>
                    )}
                </div>

                {/* Employment */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-3">
                        Employment
                    </h3>
                    {employment ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <MetaTile label="Last Country" value={employment.last_country || 'N/A'} />
                            <MetaTile label="Last Position" value={employment.last_position || 'N/A'} />
                            <MetaTile label="Employer Name" value={employment.employer_name || 'N/A'} />
                            <MetaTile label="Date of Arrival" value={formatDate(employment.date_of_arrival)} />
                        </div>
                    ) : (
                        <p className="text-[13px] italic text-slate-400">No employment history recorded</p>
                    )}
                </div>

                {/* Next of Kin */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-3">
                        Next of Kin
                    </h3>
                    {kin ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <MetaTile
                                label="Name"
                                value={[kin.first_name, kin.last_name].filter(Boolean).join(' ')}
                            />
                            <MetaTile label="Relationship" value={kin.relationship || 'N/A'} />
                            <MetaTile label="Phone Number" value={kin.phone_number || 'N/A'} />
                            <MetaTile label="Email" value={kin.email || 'N/A'} />
                        </div>
                    ) : (
                        <p className="text-[13px] italic text-slate-400">No next of kin recorded</p>
                    )}
                </div>

                {/* Case Status */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-3">
                        Case Status
                    </h3>
                    {client.has_case ? (
                        <div className="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
                            <div className="flex items-start gap-3">
                                <span className="material-symbols-outlined text-[18px] text-amber-600 mt-0.5">warning</span>
                                <div>
                                    <p className="text-[13px] font-semibold text-amber-800">Has existing case</p>
                                    <p className="text-[12px] text-amber-700 mt-0.5">
                                        This client already has an active case. Creating a new case may be rejected if a duplicate case exists in the system.
                                    </p>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <p className="text-[13px] text-slate-500">No existing case on record.</p>
                    )}
                </div>

            </div>

            {/* Footer */}
            <div className="flex justify-end gap-3 border-t border-slate-200 p-5">
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-md border border-slate-200 px-5 py-2 text-[13px] font-bold text-slate-700 hover:bg-slate-50"
                >
                    Go Back
                </button>
                <button
                    type="button"
                    onClick={() => onConfirm(client)}
                    className="rounded-md bg-indigo-600 px-5 py-2 text-[13px] font-bold text-white hover:bg-indigo-500"
                >
                    Confirm
                </button>
            </div>
        </Modal>
    );
}
