const content = `# Notifications: Staying on Top of Updates

The system notifies you when something on your cases or referrals changes — assignments, status changes, and referral activity. Notifications arrive **in-app** (the bell in the header) and, depending on your preferences, **by email**.

![Notifications page](/assets/helpdesk/notifications-page.png)

## The Notifications page

Open the bell icon or go to the **Notifications** page. Each notification card shows:

- A **severity label** and colored indicator so urgent items stand out.
- The **title** and message.
- A timestamp, a **View** link that jumps straight to the related case or referral, and **Mark as Read**.

Unread notifications are highlighted. Use **Mark All Read** to clear the backlog at once. The list is paginated (20 per page) and refreshes automatically about once a minute, so new events appear without reloading.

If you see **"No notifications yet"**, nothing needs your attention — notifications about your cases and referrals will appear here as they happen.

## Choosing what you're notified about

Go to **Profile → Notification Preferences** to toggle:

- **Email on case assigned** — you're given a new case.
- **Email on case status change** — a case you handle changes status.
- **Email on referral** — referral activity relevant to you.
- **In-app notifications** — the bell feed itself.

Email preferences only affect emails; critical in-app visibility is preserved so nothing important is silently dropped.

## Tips per role

- **Case Managers**: treat the feed as your work queue between dashboard checks — referral status changes and compliance fulfillments show up here first.
- **Agency Focal Persons**: new referrals assigned to your agency and comment replies are the ones to watch; open them via **View** to act directly.
- **Administrators**: notifications complement, but don't replace, the audit log and email logs for oversight.

> Notifications link to the underlying record. If a **View** link leads to something you can no longer access (for example, a case reassigned away from you), the page will say so — that's expected, not an error.
`;
export default content;
