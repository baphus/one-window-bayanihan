import { useEffect } from 'react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

export default function TourPrototype() {
  useEffect(() => {
    const driverObj = driver({
      showProgress: true,
      overlayColor: 'rgba(0, 0, 0, 0.6)',
      popoverClass: 'driverjs-tour',
      steps: [
        {
          element: '[data-tour="dashboard-header"]',
          popover: {
            title: 'Dashboard Header',
            description: 'View your daily greeting, the current date, and search for cases or clients.',
            side: 'bottom',
            align: 'start',
          },
        },
        {
          element: '[data-tour="dashboard-stats"]',
          popover: {
            title: 'Key Metrics',
            description: 'Monitor your Active Cases, Clients Served, Pending Referrals, and Average Resolution Time at a glance.',
            side: 'bottom',
            align: 'center',
          },
        },
        {
          element: '[data-tour="dashboard-quick-actions"]',
          popover: {
            title: 'Quick Actions',
            description: 'Quickly create new cases, referrals, or access all cases and referrals from here.',
            side: 'left',
            align: 'center',
          },
        },
      ],
    });

    const timer = setTimeout(() => driverObj.drive(), 100);
    return () => {
      clearTimeout(timer);
      driverObj.destroy();
    };
  }, []);

  return null;
}
