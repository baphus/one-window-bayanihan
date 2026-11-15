import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';
import axios from 'axios';
import ChatBot from './ChatBot';

const state = vi.hoisted(() => ({ props: { chatbot: { enabled: true, assistant_name: 'Bayani' }, auth: { user: null }, turnstile: { enabled: false, site_key: 'site-key' } } }));
vi.mock('@inertiajs/react', () => ({ usePage: () => state, Link: ({ children, ...props }) => <a {...props}>{children}</a>, router: { visit: vi.fn() } }));
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), isCancel: () => false } }));
vi.mock('@/Components/TurnstileWidget', () => ({ default: ({ onToken }) => <button onClick={() => onToken('fresh-token')}>Complete verification</button> }));

beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    state.props.auth.user = null;
    state.props.turnstile = { enabled: false, site_key: 'site-key', verified_until: null };
    global.route = (name, slug) => name === 'helpdesk.show' ? `/help/${slug}` : '/chatbot/message';
    Element.prototype.scrollTo = vi.fn();
    axios.post.mockReset();
    axios.get.mockReset().mockResolvedValue({ data: { quota: { limit: 5, remaining: 5, resets_at: '2099-01-01T16:00:00Z' } } });
});

async function send(text) {
    await waitFor(() => expect(screen.getByPlaceholderText('Ask about services, agencies, or case tracking...')).not.toBeDisabled());
    fireEvent.change(screen.getByPlaceholderText('Ask about services, agencies, or case tracking...'), { target: { value: text } });
    fireEvent.click(screen.getByRole('button', { name: 'Send message' }));
    await waitFor(() => expect(screen.getByPlaceholderText('Ask about services, agencies, or case tracking...')).not.toBeDisabled());
}

test('shows article citations and removes stale follow-up context after an unsupported answer', async () => {
    const context = { source_type: 'helpdesk', source_label: 'using-public-tracking-portal', article_title: 'Tracking guide' };
    axios.post.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: 'Use the tracker number.', sources: [{ source_type: 'helpdesk', slug: context.source_label, article_title: context.article_title }], lastContext: context } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('How do I track my case?');
    expect(screen.getByRole('link', { name: 'Tracking guide' })).toHaveAttribute('href', '/help/using-public-tracking-portal');
    // Context is kept in memory for the next turn, never written to storage.
    expect(localStorage.getItem('owb_chat_context')).toBeNull();
    axios.post.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: 'I do not have that information.', sources: [], lastContext: null } });
    await send('What is the weather?');
    expect(localStorage.getItem('owb_chat_context')).toBeNull();
    axios.post.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: 'Please clarify.', sources: [], lastContext: null } });
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
    axios.post.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: 'Hello.', sources: [], lastContext: null } });
    const { unmount } = render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('How do I track my case?');
    expect(axios.post.mock.calls[0][1].history).toEqual([]);
    expect(axios.post.mock.calls[0][1]).not.toHaveProperty('lastContext');
    unmount();

    // Simulate a browser refresh: a brand new component instance.
    axios.post.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: 'Hello again.', sources: [], lastContext: null } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('And the requirements?');
    expect(axios.post.mock.calls[1][1].history).toEqual([]);
    expect(axios.post.mock.calls[1][1]).not.toHaveProperty('lastContext');
});

test('passes in-visit history to the server so follow-up questions still resolve', async () => {
    axios.post.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: 'Use the tracker number.', sources: [], lastContext: null } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('How do I track my case?');
    axios.post.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: 'It is on the portal.', sources: [], lastContext: null } });
    await send('Where do I enter it?');
    expect(axios.post.mock.calls[1][1].history).toEqual([
        { role: 'user', text: 'How do I track my case?' },
        { role: 'bot', text: 'Use the tracker number.' },
    ]);
});

test('keeps a completed Turnstile challenge across a reload but not the transcript', async () => {
    state.props.turnstile.enabled = true;
    axios.post.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: 'Verified reply.', sources: [], lastContext: null } });
    const { unmount } = render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    // No token yet, so the first send only renders the verification prompt.
    await send('Hello there');
    expect(axios.post).not.toHaveBeenCalled();
    expect(screen.getByText(/Please verify you're human to continue chatting/i)).toBeInTheDocument();
    unmount();
    expect(sessionStorage.getItem('owb_chat_turnstile_verified')).toBeNull();
});

test('reuses server verification until expiry without replaying a token', async () => {
    state.props.turnstile.enabled = true;
    const expiry = Math.floor(Date.now() / 1000) + 60;
    const response = { data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: 'Hello', turnstile: { verified_until: expiry } } };
    axios.post.mockResolvedValue(response);
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    fireEvent.click(screen.getByRole('button', { name: 'Complete verification' }));
    await send('Hello');
    expect(axios.post.mock.calls[0][1].cf_turnstile_response).toBe('fresh-token');
    expect(screen.queryByRole('button', { name: 'Complete verification' })).not.toBeInTheDocument();
    await send('Again');
    expect(axios.post.mock.calls[1][1]).not.toHaveProperty('cf_turnstile_response');
    vi.spyOn(Date, 'now').mockReturnValue(expiry * 1000);
    await send('Expired');
    expect(axios.post).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('button', { name: 'Complete verification' })).toBeInTheDocument();
    vi.restoreAllMocks();
});

test('requires a new challenge for each message when reuse is disabled', async () => {
    state.props.turnstile.enabled = true;
    axios.post.mockResolvedValue({ data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: 'Hello', turnstile: { verified_until: null } } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    fireEvent.click(screen.getByRole('button', { name: 'Complete verification' }));
    await send('Hello');
    await send('Again');
    expect(axios.post).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('button', { name: 'Complete verification' })).toBeInTheDocument();
});

test('requires a fresh token after the server rejects a token', async () => {
    state.props.turnstile.enabled = true;
    axios.post.mockRejectedValueOnce({ response: { status: 422, data: { error: 'turnstile_failed' } } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    fireEvent.click(screen.getByRole('button', { name: 'Complete verification' }));
    await send('Hello');
    await send('Try again');
    expect(axios.post).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('button', { name: 'Complete verification' })).toBeInTheDocument();
});

test('does not show the verification prompt after a verified reload', () => {
    state.props.turnstile.enabled = true;
    state.props.turnstile.verified_until = Math.floor(Date.now() / 1000) + 60;
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    expect(screen.queryByText(/Please verify you're human to continue chatting/i)).not.toBeInTheDocument();
});

test('drops the remembered verification when the server demands a token', async () => {
    state.props.turnstile.enabled = true;
    state.props.turnstile.verified_until = Math.floor(Date.now() / 1000) + 60;
    const error = Object.assign(new Error('turnstile_required'), { response: { status: 422, data: { error: 'turnstile_required' } } });
    axios.post.mockRejectedValueOnce(error);
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('Hello there');
    expect(axios.post.mock.calls[0][1]).not.toHaveProperty('cf_turnstile_response');
    expect(await screen.findByText(/Please complete the verification challenge below to continue/i)).toBeInTheDocument();
});

test('renders only approved navigation links from replies', async () => {
    axios.post.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 4, resets_at: '2099-01-01T16:00:00Z' }, reply: '[Partners](/partners) [Contact](/contact) [Track](/track) [External](https://evil.example/track) [Script](javascript:alert(1)) [Query](/track?x=1)' } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await send('Help');
    expect(await screen.findByRole('link', { name: 'Partners' }, { timeout: 5000 })).toHaveAttribute('href', '/partners');
    expect(await screen.findByText('Query', {}, { timeout: 5000 })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Contact' })).toHaveAttribute('href', '/contact');
    expect(screen.getByRole('link', { name: 'Track' })).toHaveAttribute('href', '/track');
    expect(screen.queryByRole('link', { name: 'External' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Script' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Query' })).not.toBeInTheDocument();
});

test('loads allowance, updates it on reply, and distinguishes quota exhaustion from other throttling', async () => {
    axios.post.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 1, resets_at: '2099-01-01T16:00:00Z' }, reply: 'Hello' } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    expect(await screen.findByText(/5 of 5 messages remaining/)).toBeInTheDocument();
    await send('Hello');
    expect(screen.getByText(/1 of 5 messages remaining/)).toBeInTheDocument();
    axios.post.mockRejectedValueOnce({ response: { status: 429, data: {} } });
    await send('Again');
    expect(screen.getByText(/1 of 5 messages remaining/)).toBeInTheDocument();
    axios.post.mockRejectedValueOnce({ response: { status: 429, data: { status: 'quota_exceeded', message: 'Daily limit reached.', quota: { limit: 5, remaining: 0, resets_at: '2099-01-01T16:00:00Z' } } } });
    fireEvent.change(screen.getByPlaceholderText('Ask about services, agencies, or case tracking...'), { target: { value: 'Last' } });
    fireEvent.click(screen.getByRole('button', { name: 'Send message' }));
    expect(await screen.findByText(/0 of 5 messages remaining/)).toHaveTextContent('Asia/Manila');
    expect(screen.getByRole('button', { name: 'Send message' })).toBeDisabled();
    expect(screen.getByRole('link', { name: 'Track a case' })).toHaveAttribute('href', '/track');
});

test('refreshes exhausted allowance at reset and when reopened', async () => {
    axios.get.mockResolvedValueOnce({ data: { quota: { limit: 5, remaining: 0, resets_at: new Date(Date.now() + 50).toISOString() } } });
    render(<ChatBot />);
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    expect(await screen.findByText(/0 of 5 messages remaining/)).toBeInTheDocument();
    expect(await screen.findByText(/5 of 5 messages remaining/, {}, { timeout: 4000 })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Close chat' }));
    fireEvent.click(screen.getByRole('button', { name: 'Open chat' }));
    await waitFor(() => expect(axios.get).toHaveBeenCalledTimes(3));
});
