import countries from '@/data/countries.json';
import SearchableSelect from '@/Components/SearchableSelect';

const countryOptions = countries.map((c) => ({ value: c.name, label: c.name }));

export default function CountrySelect({ value, onChange, placeholder = 'Select country...', disabled }) {
    return (
        <SearchableSelect
            value={value}
            onChange={onChange}
            options={countryOptions}
            placeholder={placeholder}
            disabled={disabled}
        />
    );
}
