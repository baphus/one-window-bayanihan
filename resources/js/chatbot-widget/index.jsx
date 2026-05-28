import { createRoot } from 'react-dom/client';
import ChatbotWidget from './ChatbotWidget';
import './ChatbotWidget.css';

const DEFAULT_CONFIG = {
  apiEndpoint: '/chatbot/message',
  title: 'Help Center',
  primaryColor: '#2563eb',
  greeting: 'Hello! How can I help you today?',
};

export function mountChatbotWidget(containerId, config = {}) {
  const container = document.getElementById(containerId);
  if (!container) {
    console.error(`Chatbot widget: container #${containerId} not found`);
    return;
  }

  const mergedConfig = { ...DEFAULT_CONFIG, ...config };
  const root = createRoot(container);
  root.render(<ChatbotWidget config={mergedConfig} />);

  return root;
}
