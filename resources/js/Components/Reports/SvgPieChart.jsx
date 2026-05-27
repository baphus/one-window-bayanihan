export default function SvgPieChart({ data, className = 'w-16 h-16' }) {
  const total = data.reduce((sum, item) => sum + item.count, 0) || 1;
  let cumulativePercent = 0;

  return (
    <svg viewBox="0 0 63.6619772 63.6619772" className={`${className} -rotate-90 rounded-full shrink-0`}>
      <circle cx="31.8309886" cy="31.8309886" r="31.8309886" fill="#f1f5f9" />
      {data.map((item) => {
        const pct = (item.count / total) * 100;
        const offset = 100 - cumulativePercent;
        const strokeDasharray = `${pct} ${100 - pct}`;
        cumulativePercent += pct;

        if (pct === 0) return null;

        return (
          <circle
            key={item.label}
            r="15.915494309189533"
            cx="31.8309886"
            cy="31.8309886"
            fill="transparent"
            stroke={item.hex}
            strokeWidth="31.8309886"
            strokeDasharray={strokeDasharray}
            strokeDashoffset={offset}
            className="cursor-pointer hover:opacity-80 transition-opacity outline-none"
          >
            <title>{item.label}: {item.count}</title>
          </circle>
        );
      })}
    </svg>
  );
}
