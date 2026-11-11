/** Mirrors App\Models\User::composeName for client-side previews. */
export default function displayName(user) {
  if (!user) return '';
  const given = [user.first_name, user.middle_name].filter(Boolean).join(' ');
  const base = [given, user.last_name].filter(Boolean).join(' ').trim();
  const suffix = (user.suffix ?? '').trim();
  return suffix ? `${base}, ${suffix}` : base || user.name || '';
}
