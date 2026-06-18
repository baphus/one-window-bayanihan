import React from 'react';
import Section from '@/Components/Section';
import ToggleSwitch from '@/Components/ui/ToggleSwitch';

const TOGGLES = [
  {
    key: 'email_on_case_assigned',
    label: 'Email when a case is assigned to me',
    description: 'Receive an email notification when a new case is assigned to you.',
  },
  {
    key: 'email_on_case_status_change',
    label: 'Email when case status changes',
    description: "Get notified when the status of a case you're involved with changes.",
  },
  {
    key: 'email_on_referral',
    label: 'Email when I receive a referral',
    description: 'Receive an email when another user refers a case to you.',
  },
  {
    key: 'in_app_notifications',
    label: 'In-app notifications',
    description: 'Show notification badges and alerts within the application.',
  },
];

export default function NotificationPreferencesSection({ prefs = {}, onToggle }) {
  return (
    <Section
      title="Notification Preferences"
      description="Choose which notifications you'd like to receive."
    >
      {TOGGLES.map(({ key, label, description }) => (
        <ToggleSwitch
          key={key}
          checked={prefs[key] ?? false}
          onChange={(value) => onToggle(key, value)}
          label={label}
          description={description}
        />
      ))}
    </Section>
  );
}
