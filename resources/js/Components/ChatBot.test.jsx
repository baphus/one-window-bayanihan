import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';
import axios from 'axios';
import ChatBot from './ChatBot';

const state = vi.hoisted(() => ({ props: { chatbot: { enabled: true, assistant_name: 'Bayani' }, auth: { user: null }, turnstile: { enabled: false, site_key: 'site-key' } } }));
vi.mock('@inertiajs/react', () => ({ usePage: () => state, Link: ({ children, ...props }) => <a {...props}>{children}</a>, router: { visit: vi.fn() } }));
vi.mock('axios', () => ({ default: { post: vi.fn(), isCancel: () => false } }));
vi.mock('@/Components/TurnstileWidget', () => ({ default: () => null }));

beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    state.props.auth.user = null;
    state.props.turnstile.enabled = false;
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
    // Context is kept in memory for the next turn, never written to storage.
    expect(localStorage.getItem('owb_chat_context')).toBeNull();
    axios.post.mockResolvedValueOnce({ data: { reply: 'I do not have that information.', sources: [], lastContext: null } });
    await send('What is the weather?');
    expect(localStorage.getItem('owb_chat_context')).toBeNull();
    axios.post.mockResolvedValueOnce({ data: { reply: 'Please clarify.', sources: [], lastContext: null } });
    await send('Another topic');
    expect(axios.post.mock.calls[2][1]).not.toHaveProperty('lastContext');
});

test('ignores any persisted conversation so a page load starts clean', () => {
    localStorage.setItem('owb_chat_history', JSON.stringify([{ role: 'bot', text: 'Legacy answer' }]));
    localStorage.setItem('owb_chat_context', JSON.stringify({ source_type: 'helpdesk', source_label: 'old', article_title: 'Old' }));
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    expect(screen.queryByText('Legacy answer')).not.toBeInTheDocument();
});

test('starts the first request after a reload with no history or context', async () => {
    axios.post.mockResolvedValueOnce({ data: { reply: 'Hello.', sources: [], lastContext: null } });
    const { unmount } = render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('How do I track my case?');
    expect(axios.post.mock.calls[0][1].history).toEqual([]);
    expect(axios.post.mock.calls[0][1]).not.toHaveProperty('lastContext');
    unmount();

    // Simulate a browser refresh: a brand new component instance.
    axios.post.mockResolvedValueOnce({ data: { reply: 'Hello again.', sources: [], lastContext: null } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('And the requirements?');
    expect(axios.post.mock.calls[1][1].history).toEqual([]);
    expect(axios.post.mock.calls[1][1]).not.toHaveProperty('lastContext');
});

test('passes in-visit history to the server so follow-up questions still resolve', async () => {
    axios.post.mockResolvedValueOnce({ data: { reply: 'Use the tracker number.', sources: [], lastContext: null } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('How do I track my case?');
    axios.post.mockResolvedValueOnce({ data: { reply: 'It is on the portal.', sources: [], lastContext: null } });
    await send('Where do I enter it?');
    expect(axios.post.mock.calls[1][1].history).toEqual([
        { role: 'user', text: 'How do I track my case?' },
        { role: 'bot', text: 'Use the tracker number.' },
    ]);
});

test('keeps a completed Turnstile challenge across a reload but not the transcript', async () => {
    state.props.turnstile.enabled = true;
    axios.post.mockResolvedValueOnce({ data: { reply: 'Verified reply.', sources: [], lastContext: null } });
    const { unmount } = render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    // No token yet, so the first send only renders the verification prompt.
    await send('Hello there');
    expect(axios.post).not.toHaveBeenCalled();
    expect(screen.getByText(/Please verify you're human to continue chatting/i)).toBeInTheDocument();
    unmount();
    expect(sessionStorage.getItem('owb_chat_turnstile_verified')).toBeNull();
});

test('does not show the verification prompt after a verified reload', () => {
    state.props.turnstile.enabled = true;
    sessionStorage.setItem('owb_chat_turnstile_verified', '1');
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    expect(screen.queryByText(/Please verify you're human to continue chatting/i)).not.toBeInTheDocument();
});

test('drops the remembered verification when the server demands a token', async () => {
    state.props.turnstile.enabled = true;
    sessionStorage.setItem('owb_chat_turnstile_verified', '1');
    const error = Object.assign(new Error('turnstile_required'), { response: { status: 422, data: { error: 'turnstile_required' } } });
    axios.post.mockRejectedValueOnce(error);
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('Hello there');
    expect(axios.post.mock.calls[0][1]).not.toHaveProperty('cf_turnstile_response');
    expect(await screen.findByText(/Please complete the verification challenge below to continue/i)).toBeInTheDocument();
});
