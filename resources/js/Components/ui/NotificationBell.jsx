import { Bell } from 'lucide-react'
import { useState } from 'react'

export default function NotificationBell({ notifications = [] }) {
  const [open, setOpen] = useState(false)

  const unread = notifications.length

  return (
    <div className="relative">
      <button
        onClick={() => setOpen(!open)}
        onBlur={() => setTimeout(() => setOpen(false), 200)}
        className="relative p-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 transition-all shadow-sm"
      >
        <Bell className="w-5 h-5 text-blue-900" />
        {unread > 0 && (
          <span className="absolute -top-1 -right-1 flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-orange-500 rounded-full">
            {unread}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl border border-slate-200 shadow-lg z-50 overflow-hidden">
          <div className="px-4 py-3 border-b border-slate-100">
            <h4 className="text-[11px] font-bold uppercase tracking-widest text-blue-900">Notifications</h4>
          </div>
          <div className="max-h-64 overflow-y-auto">
            {notifications.length === 0 ? (
              <p className="px-4 py-6 text-center text-xs text-slate-400">No notifications</p>
            ) : (
              notifications.map((n, i) => (
                <div key={n.id ?? i} className="px-4 py-3 border-b border-slate-50 last:border-b-0 hover:bg-slate-50 transition-colors">
                  <p className="text-xs font-bold text-slate-900">{n.title}</p>
                  <p className="text-[11px] text-slate-500 mt-0.5 leading-relaxed">{n.message}</p>
                  <p className="text-[9px] font-bold uppercase tracking-widest text-blue-800 mt-1">{n.time}</p>
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  )
}
