/**
 * Calendar-date helpers shared by the Reports DateRangePicker and
 * report filter hooks. Kept in lib so non-component modules never
 * import from a component file.
 */

export function addDays(date, days) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
}

export function toISODateInputValue(date) {
  const year = date.getFullYear();
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const day = `${date.getDate()}`.padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function getQuickRangeDates(option) {
  const toDate = new Date();
  let fromDate = new Date(toDate.getFullYear(), toDate.getMonth(), toDate.getDate());

  if (option === '7_DAYS') fromDate = addDays(toDate, -6);
  if (option === '14_DAYS') fromDate = addDays(toDate, -13);
  if (option === '30_DAYS') fromDate = addDays(toDate, -29);
  const months = { '3_MONTHS': 3, '6_MONTHS': 6, '9_MONTHS': 9, '1_YEAR': 12 }[option];
  if (months) {
    const targetMonth = new Date(toDate.getFullYear(), toDate.getMonth() - months, 1);
    const lastDay = new Date(targetMonth.getFullYear(), targetMonth.getMonth() + 1, 0).getDate();
    fromDate = new Date(targetMonth.getFullYear(), targetMonth.getMonth(), Math.min(toDate.getDate(), lastDay));
  }

  return { fromISO: toISODateInputValue(fromDate), toISO: toISODateInputValue(toDate) };
}
