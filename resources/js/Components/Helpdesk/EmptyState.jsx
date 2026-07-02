export default function EmptyState({ icon = 'help', title, description, action }) {
  return (
    <div className="flex flex-col items-center justify-center border border-dashed border-outline-variant bg-white py-16 text-center">
      <span className="material-symbols-outlined mb-4 text-5xl text-primary/30">
        {icon}
      </span>
      <h3 className="font-headline text-base font-semibold text-slate-700">{title}</h3>
      {description && (
        <p className="mt-1 max-w-md text-sm text-slate-500">{description}</p>
      )}
      {action && (
        <div className="mt-4">{action}</div>
      )}
    </div>
  );
}
