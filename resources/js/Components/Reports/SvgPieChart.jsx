import { Doughnut } from 'react-chartjs-2';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';

ChartJS.register(ArcElement, Tooltip, Legend);

export default function SvgPieChart({ data, className = 'w-16 h-16' }) {
  if (!data || data.length === 0) return null;

  const chartData = {
    labels: data.map((item) => item.label),
    datasets: [{
      data: data.map((item) => item.count),
      backgroundColor: data.map((item) => item.hex),
      borderWidth: 0,
    }],
  };

  const sizeMatch = className ? className.match(/w-(\d+)/) : null;
  const size = sizeMatch ? parseInt(sizeMatch[1]) * 4 : 64;

  return (
    <div style={{ width: size, height: size }}>
      <Doughnut
        data={chartData}
        options={{
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { enabled: true } },
          cutout: '50%',
        }}
      />
    </div>
  );
}
