import { Link } from '@inertiajs/react';

export default function AppButton({ children, variant = 'primary', className = '', icon, ...props }) {
  const base = 'inline-flex items-center justify-center gap-2 font-bold transition-all';
  const sizes = 'px-6 py-2.5 text-[14px]';
  const styles = {
    primary: 'bg-primary text-white hover:brightness-110 active:scale-95',
    outline: 'border border-outline-variant text-primary hover:bg-slate-50',
    mint: 'bg-secondary-container text-on-secondary-container hover:bg-secondary-fixed-dim disabled:cursor-not-allowed disabled:opacity-60',
    'outline-primary': 'border-2 border-primary px-6 py-3',
  };

  const cls = `${base} ${sizes} ${styles[variant] || styles.primary} ${className}`;

  if (props.as === 'link' && props.href) {
    return (
      <Link href={props.href} className={`${cls} rounded-none`}>
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {children}
      </Link>
    );
  }

  if (props.href) {
    return (
      <a href={props.href} className={`${cls} rounded-none`} {...props}>
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {children}
      </a>
    );
  }

  return (
    <button type="button" className={`${cls} rounded-none`} {...props}>
      {icon && <span className="material-symbols-outlined">{icon}</span>}
      {children}
    </button>
  );
}
