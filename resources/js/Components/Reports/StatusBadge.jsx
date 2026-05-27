const colorMap = {
  PENDING: 'border-[#fde68a] bg-[#fef3c7] text-[#b45309]',
  PROCESSING: 'border-[#bae6fd] bg-[#e0f2fe] text-[#0369a1]',
  FOR_COMPLIANCE: 'border-[#fed7aa] bg-[#ffedd5] text-[#c2410c]',
  COMPLETED: 'border-[#bbf7d0] bg-[#dcfce7] text-[#15803d]',
  REJECTED: 'border-[#fecaca] bg-[#fee2e2] text-[#b91c1c]',
  OPEN: 'border-[#bbf7d0] bg-[#f0fdf4] text-[#166534]',
  CLOSED: 'border-[#cbd5e1] bg-[#f8fafc] text-[#475569]',
};

export default function StatusBadge({ status }) {
  return (
    <span
      className={`inline-flex rounded-[2px] border px-2 py-1 text-[10px] font-extrabold tracking-wide ${colorMap[status] || 'border-[#cbd5e1] bg-white text-slate-600'}`}
    >
      {status}
    </span>
  );
}
