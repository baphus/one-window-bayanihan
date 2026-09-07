import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';
import axios from 'axios';
import ChatBot from './ChatBot';

const state = vi.hoisted(() => ({ props: { chatbot: { enabled: true, assistant_name: 'Bayani' }, auth: { user: null }, turnstile: { enabled: false } } }));
vi.mock('@inertiajs/react', () => ({ usePage: () => state, Link: ({ children, ...props }) => <a {...props}>{children}</a>, router: { visit: vi.fn() } }));
vi.mock('axios', () => ({ default: { post: vi.fn(), isCancel: () => false } }));
vi.mock('@/Components/TurnstileWidget', () => ({ default: () => null }));

beforeEach(() => {
    localStorage.clear();
    localStorage.setItem('owb_chat_owner', 'public:public');
    state.props.auth.user = null;
    global.route = (name, slug) => name === 'helpdesk.show' ? `/help/${slug}` : '/chatbot/message';
    Element.prototype.scrollTo = vi.fn();
    axios.post.mockReset();
});

async function send(text) {
    fireEvent.change(screen.getByPlaceholderText('Ask about services, agencies, or case tracking...'), { target: { value: text } });
    fireEvent.click(screen.getByRole('button', { name: 'Send message' }));
    await waitFor(() => expect(screen.getByPlaceholderText('Ask about services, agencies, or case tracking...')).not.toBeDisabled());
}

test('shows article citations and removes stale follow-up context after an unsupported answer', async () => {
    const context = { source_type: 'helpdesk', source_label: 'using-public-tracking-portal', article_title: 'Tracking guide' };
    axios.post.mockResolvedValueOnce({ data: { reply: 'Use the tracker number.', sources: [{ source_type: 'helpdesk', slug: context.source_label, article_title: context.article_title }], lastContext: context } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('How do I track my case?');
    expect(screen.getByRole('link', { name: 'Tracking guide' })).toHaveAttribute('href', '/help/using-public-tracking-portal');
    expect(localStorage.getItem('owb_chat_context')).not.toBeNull();
    axios.post.mockResolvedValueOnce({ data: { reply: 'I do not have that information.', sources: [], lastContext: null } });
    await send('What is the weather?');
    expect(localStorage.getItem('owb_chat_context')).toBeNull();
    axios.post.mockResolvedValueOnce({ data: { reply: 'Please clarify.', sources: [], lastContext: null } });
    await send('Another topic');
    expect(axios.post.mock.calls[2][1]).not.toHaveProperty('lastContext');
});

test('loads legacy messages and clears them when identity changes', () => {
    localStorage.setItem('owb_chat_history', JSON.stringify([{ role: 'bot', text: 'Legacy answer' }]));
    localStorage.setItem('owb_chat_context', JSON.stringify({ source_label: 'old' }));
    const { rerender } = render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    expect(screen.getByText('Legacy answer')).toBeInTheDocument();
    state.props.auth.user = { id: 'another-user', role: 'AGENCY' };
    rerender(<ChatBot />);
    expect(screen.queryByText('Legacy answer')).not.toBeInTheDocument();
    expect(localStorage.getItem('owb_chat_context')).toBeNull();
});
