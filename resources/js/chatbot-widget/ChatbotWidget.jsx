import React, { useState, useRef, useEffect, useCallback } from 'react';

const LS_KEY = 'owb_chatbot_messages';

function loadMessages() {
  try {
    const raw = sessionStorage.getItem(LS_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function saveMessages(messages) {
  try {
    sessionStorage.setItem(LS_KEY, JSON.stringify(messages));
  } catch {
    // sessionStorage full or unavailable — silently fail
  }
}

export default function ChatbotWidget({ config }) {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState(loadMessages);
  const [input, setInput] = useState('');
  const [isTyping, setIsTyping] = useState(false);
  const [error, setError] = useState(null);
  const listRef = useRef(null);
  const inputRef = useRef(null);

  // Auto-scroll to bottom on new messages
  useEffect(() => {
    if (listRef.current) {
      listRef.current.scrollTop = listRef.current.scrollHeight;
    }
  }, [messages, isTyping]);

  // Focus input when panel opens
  useEffect(() => {
    if (isOpen && inputRef.current) {
      inputRef.current.focus();
    }
  }, [isOpen]);

  const sendMessage = useCallback(async (text) => {
    const trimmed = text.trim();
    if (!trimmed) return;

    const userMsg = { role: 'user', content: trimmed };
    const updated = [...messages, userMsg];
    setMessages(updated);
    saveMessages(updated);

    setInput('');
    setIsTyping(true);
    setError(null);

    try {
      const res = await fetch(config.apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: trimmed }),
      });

      if (!res.ok) {
        throw new Error(`Server returned ${res.status}`);
      }

      const data = await res.json();
      const assistantMsg = { role: 'assistant', content: data.reply ?? '' };
      const final = [...updated, assistantMsg];
      setMessages(final);
      saveMessages(final);
    } catch (err) {
      setError(err.message);
    } finally {
      setIsTyping(false);
    }
  }, [config.apiEndpoint, messages]);

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage(input);
    }
  };

  const handleReset = () => {
    setMessages([]);
    setError(null);
    sessionStorage.removeItem(LS_KEY);
  };

  return (
    <div className="owb-chat-widget">
      {/* Chat button */}
      <button
        className={`owb-chat-button ${isOpen ? 'owb-chat-button--open' : ''}`}
        onClick={() => setIsOpen(!isOpen)}
        aria-label={isOpen ? 'Close chat' : 'Open chat'}
      >
        {isOpen ? (
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M18 6L6 18M6 6l12 12" />
          </svg>
        ) : (
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
          </svg>
        )}
      </button>

      {/* Chat panel */}
      {isOpen && (
        <div className="owb-chat-panel">
          {/* Header */}
          <div
            className="owb-chat-header"
            style={{ backgroundColor: config.primaryColor }}
          >
            <span className="owb-chat-header-title">{config.title}</span>
            <button
              className="owb-chat-header-reset"
              onClick={handleReset}
              aria-label="Reset conversation"
              title="Reset conversation"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M23 4v6h-6M1 20v-6h6" />
                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" />
              </svg>
            </button>
          </div>

          {/* Messages */}
          <div className="owb-chat-messages" ref={listRef}>
            {messages.length === 0 && !isTyping && (
              <div className="owb-chat-empty">
                <p>{config.greeting}</p>
              </div>
            )}

            {messages.map((msg, i) => (
              <div
                key={i}
                className={`owb-chat-bubble owb-chat-bubble--${msg.role === 'user' ? 'user' : 'assistant'
                }`}
              >
                {msg.content}
              </div>
            ))}

            {isTyping && (
              <div className="owb-chat-bubble owb-chat-bubble--assistant owb-chat-typing">
                <span className="owb-chat-typing-dot" />
                <span className="owb-chat-typing-dot" />
                <span className="owb-chat-typing-dot" />
              </div>
            )}

            {error && (
              <div className="owb-chat-error">
                <p>Something went wrong. Please try again.</p>
                <button
                  className="owb-chat-error-retry"
                  onClick={() => sendMessage(messages[messages.length - 1]?.content ?? input)}
                >
                  Retry
                </button>
              </div>
            )}
          </div>

          {/* Input */}
          <div className="owb-chat-input-area">
            <textarea
              ref={inputRef}
              className="owb-chat-input"
              placeholder="Type your question..."
              rows={1}
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={handleKeyDown}
              disabled={isTyping}
            />
            <button
              className="owb-chat-send"
              onClick={() => sendMessage(input)}
              disabled={isTyping || !input.trim()}
              aria-label="Send message"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M22 2L11 13" />
                <path d="M22 2l-7 20-4-9-9-4 20-7z" />
              </svg>
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
