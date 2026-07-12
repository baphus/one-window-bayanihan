import countries from '@/data/countries.json';

export default function CountrySelect({ value, onChange, placeholder, error }) {
    return (
        <select
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className="h-10 w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
        >
            {placeholder && <option value="">{placeholder}</option>}
            {countries.map((c) => (
                <option key={c.code} value={c.name}>{c.name}</option>
            ))}
        </select>
    );
}
