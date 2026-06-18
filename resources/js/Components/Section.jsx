export default function Section({ title, description, children }) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      {(title || description) && (
        <div className="mb-6">
          {title && <h2 className="text-base font-semibold text-slate-900">{title}</h2>}
          {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
        </div>
      )}
      <div className="space-y-4">{children}</div>
    </div>
  );
}
