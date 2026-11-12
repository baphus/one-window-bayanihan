import InputError from '@/Components/InputError';

const INPUT = {
  default: 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm',
  brand: 'w-full border border-outline-variant bg-surface-container px-4 py-2.5 text-sm focus:border-primary focus:outline-none rounded-md',
};

const LABEL = {
  default: 'block text-sm font-medium text-slate-700',
  brand: 'mb-1 block text-xs font-bold uppercase tracking-widest text-on-surface-variant',
};

/**
 * The six identity columns every account shares. `required` marks the two
 * names for everyone; `requireDemographics` adds OFW's mandatory birth date
 * and sex (they drive age-based assessment on intake).
 */
export function emptyIdentity(user) {
  return {
    first_name: user?.first_name ?? '',
    middle_name: user?.middle_name ?? '',
    last_name: user?.last_name ?? '',
    suffix: user?.suffix ?? '',
    date_of_birth: user?.date_of_birth ?? '',
    sex: user?.sex ?? '',
  };
}

export default function IdentityFields({ data, setData, errors = {}, requireDemographics = false, variant = 'default' }) {
  const input = INPUT[variant] ?? INPUT.default;
  const label = LABEL[variant] ?? LABEL.default;
  const error = (name) => <InputError message={errors[name]} className="mt-1" />;
  const field = (name, text, required = false) => (
    <div>
      <label className={label} htmlFor={`identity-${name}`}>
        {text}{required && <span className="text-red-500"> *</span>}
      </label>
      <input
        id={`identity-${name}`}
        type="text"
        value={data[name] ?? ''}
        onChange={(event) => setData(name, event.target.value)}
        className={input}
        required={required}
        maxLength={255}
        autoComplete={name === 'first_name' ? 'given-name' : name === 'middle_name' ? 'additional-name' : name === 'last_name' ? 'family-name' : 'off'}
      />
      {error(name)}
    </div>
  );

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      {field('first_name', 'First name', true)}
      {field('middle_name', 'Middle name')}
      {field('last_name', 'Last name', true)}
      <div>
        <label className={label} htmlFor="identity-suffix">
          Suffix
        </label>
        <select
          id="identity-suffix"
          value={data.suffix ?? ''}
          onChange={(event) => setData('suffix', event.target.value)}
          className={input}
          autoComplete="honorific-suffix"
        >
          <option value="">None</option>
          <option value="Jr.">Jr.</option>
          <option value="Sr.">Sr.</option>
          <option value="II">II</option>
          <option value="III">III</option>
          <option value="IV">IV</option>
          <option value="V">V</option>
        </select>
        {error('suffix')}
      </div>
      <div>
        <label className={label} htmlFor="identity-date_of_birth">
          Date of birth{requireDemographics && <span className="text-red-500"> *</span>}
        </label>
        <input
          id="identity-date_of_birth"
          type="date"
          value={data.date_of_birth ?? ''}
          onChange={(event) => setData('date_of_birth', event.target.value)}
          className={input}
          required={requireDemographics}
          autoComplete="bday"
        />
        {error('date_of_birth')}
      </div>
      <div>
        <label className={label} htmlFor="identity-sex">
          Sex{requireDemographics && <span className="text-red-500"> *</span>}
        </label>
        <select
          id="identity-sex"
          value={data.sex ?? ''}
          onChange={(event) => setData('sex', event.target.value)}
          className={input}
          required={requireDemographics}
        >
          <option value="">Select sex...</option>
          <option value="MALE">Male</option>
          <option value="FEMALE">Female</option>
        </select>
        {error('sex')}
      </div>
    </div>
  );
}
