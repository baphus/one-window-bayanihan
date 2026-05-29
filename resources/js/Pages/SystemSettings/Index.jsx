import { useState, useRef, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

export default function SystemSettings({ 
    debug_otp_enabled, 
    referral_overdue_days,
    chatbot_enabled,
    chatbot_provider,
    chatbot_model,
    chatbot_system_prompt,
    chatbot_temperature,
    chatbot_max_tokens,
    chatbot_custom_endpoint,
    has_chatbot_api_key,
}) {
    const [debugOtp, setDebugOtp] = useState(debug_otp_enabled);
    const [overdueDays, setOverdueDays] = useState(referral_overdue_days);
    
    const [chatbotEnabled, setChatbotEnabled] = useState(chatbot_enabled);
    const [chatbotProvider, setChatbotProvider] = useState(chatbot_provider);
    const [chatbotApiKey, setChatbotApiKey] = useState('');
    const [chatbotModel, setChatbotModel] = useState(chatbot_model);
    const [chatbotSystemPrompt, setChatbotSystemPrompt] = useState(chatbot_system_prompt);
    const [chatbotTemperature, setChatbotTemperature] = useState(chatbot_temperature);
    const [chatbotMaxTokens, setChatbotMaxTokens] = useState(chatbot_max_tokens);
    const [chatbotCustomEndpoint, setChatbotCustomEndpoint] = useState(chatbot_custom_endpoint);

    const initialRef = useRef({ 
        debugOtp: debug_otp_enabled, 
        overdueDays: referral_overdue_days,
        chatbotEnabled: chatbot_enabled,
        chatbotProvider: chatbot_provider,
        chatbotApiKey: '',
        chatbotModel: chatbot_model,
        chatbotSystemPrompt: chatbot_system_prompt,
        chatbotTemperature: chatbot_temperature,
        chatbotMaxTokens: chatbot_max_tokens,
        chatbotCustomEndpoint: chatbot_custom_endpoint,
    });
    
    const hasDirty = useMemo(() => (
        debugOtp !== initialRef.current.debugOtp
        || overdueDays !== initialRef.current.overdueDays
        || chatbotEnabled !== initialRef.current.chatbotEnabled
        || chatbotProvider !== initialRef.current.chatbotProvider
        || chatbotApiKey !== initialRef.current.chatbotApiKey
        || chatbotModel !== initialRef.current.chatbotModel
        || chatbotSystemPrompt !== initialRef.current.chatbotSystemPrompt
        || chatbotTemperature !== initialRef.current.chatbotTemperature
        || chatbotMaxTokens !== initialRef.current.chatbotMaxTokens
        || chatbotCustomEndpoint !== initialRef.current.chatbotCustomEndpoint
    ), [debugOtp, overdueDays, chatbotEnabled, chatbotProvider, chatbotApiKey, chatbotModel, chatbotSystemPrompt, chatbotTemperature, chatbotMaxTokens, chatbotCustomEndpoint]);
    const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasDirty);

    const saveChatbotSettings = () => {
        bypassNext();
        router.post(route('admin.system-settings.update'), {
            ...(chatbotEnabled !== initialRef.current.chatbotEnabled && { chatbot_enabled: chatbotEnabled }),
            ...(chatbotProvider !== initialRef.current.chatbotProvider && { chatbot_provider: chatbotProvider }),
            ...(chatbotApiKey && { chatbot_api_key: chatbotApiKey }),
            ...(chatbotModel !== initialRef.current.chatbotModel && { chatbot_model: chatbotModel }),
            ...(chatbotSystemPrompt !== initialRef.current.chatbotSystemPrompt && { chatbot_system_prompt: chatbotSystemPrompt }),
            ...(chatbotTemperature !== initialRef.current.chatbotTemperature && { chatbot_temperature: chatbotTemperature }),
            ...(chatbotMaxTokens !== initialRef.current.chatbotMaxTokens && { chatbot_max_tokens: chatbotMaxTokens }),
            ...(chatbotCustomEndpoint !== initialRef.current.chatbotCustomEndpoint && { chatbot_custom_endpoint: chatbotCustomEndpoint }),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setChatbotApiKey('');
                initialRef.current = {
                    ...initialRef.current,
                    chatbotEnabled,
                    chatbotProvider,
                    chatbotApiKey: '',
                    chatbotModel,
                    chatbotSystemPrompt,
                    chatbotTemperature,
                    chatbotMaxTokens,
                    chatbotCustomEndpoint,
                };
            },
        });
    };

    const toggleDebugOtp = () => {
        const next = !debugOtp;
        setDebugOtp(next);
        bypassNext();
        router.post(route('admin.system-settings.update'), {
            debug_otp_enabled: next,
            referral_overdue_days: overdueDays,
        }, {
            preserveScroll: true,
            onError: () => setDebugOtp(!next),
        });
    };

    const saveOverdueDays = () => {
        bypassNext();
        router.post(route('admin.system-settings.update'), {
            debug_otp_enabled: debugOtp,
            referral_overdue_days: overdueDays,
        }, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout title="System Settings">
            <Head title="System Settings" />
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-slate-900">System Settings</h1>
                <p className="text-sm text-slate-500 mt-1">Manage system-wide configuration and preferences.</p>
            </div>

            <div className="grid grid-cols-1 gap-6 max-w-2xl">
                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <h3 className="text-base font-semibold text-slate-900 mb-4">Application Information</h3>
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-slate-500">Application Name</dt>
                            <dd className="font-medium text-slate-900">One Window Bayanihan</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-slate-500">Version</dt>
                            <dd className="font-medium text-slate-900">1.0.0</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-slate-500">Region</dt>
                            <dd className="font-medium text-slate-900">Central Visayas (Region VII)</dd>
                        </div>
                    </dl>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <h3 className="text-base font-semibold text-slate-900 mb-4">SERVQUAL Configuration</h3>
                    <p className="text-sm text-slate-600">
                        SERVQUAL (Service Quality) dimensions and parameters are used to measure client satisfaction across agencies.
                        Configuration management will be available in a future update.
                    </p>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <h3 className="text-base font-semibold text-slate-900 mb-4">Referral Overdue Threshold</h3>
                    <p className="text-sm text-slate-600 mb-4">
                        Referrals are marked overdue when they exceed this number of days without being completed or rejected.
                    </p>
                    <div className="flex items-end gap-3">
                        <div className="flex-1">
                            <label className="block text-sm font-medium text-slate-700 mb-1">Overdue after (days)</label>
                            <input
                                type="number"
                                min="1"
                                max="365"
                                value={overdueDays}
                                onChange={(e) => setOverdueDays(parseInt(e.target.value) || 7)}
                                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                        </div>
                        <button
                            onClick={saveOverdueDays}
                            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500"
                        >
                            Save
                        </button>
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <h3 className="text-base font-semibold text-slate-900 mb-4">OTP Settings</h3>
                    <p className="text-sm text-slate-600">
                        One-Time Password settings for the public case tracking system.
                    </p>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h3 className="text-base font-semibold text-slate-900">AI Chatbot Configuration</h3>
                            <p className="text-sm text-slate-500 mt-1">
                                Configure the AI assistant for case summaries and recommendations.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={chatbotEnabled}
                            onClick={() => setChatbotEnabled(!chatbotEnabled)}
                            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${chatbotEnabled ? 'bg-indigo-600' : 'bg-slate-300'}`}
                        >
                            <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${chatbotEnabled ? 'translate-x-5' : 'translate-x-0'}`} />
                        </button>
                    </div>

                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Provider</label>
                            <select
                                value={chatbotProvider}
                                onChange={(e) => setChatbotProvider(e.target.value)}
                                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                <option value="openai">OpenAI</option>
                                <option value="anthropic">Anthropic (Claude)</option>
                                <option value="gemini">Google Gemini</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>

                        {chatbotProvider === 'custom' && (
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Custom Endpoint URL</label>
                                <input
                                    type="text"
                                    value={chatbotCustomEndpoint || ''}
                                    onChange={(e) => setChatbotCustomEndpoint(e.target.value)}
                                    placeholder="https://api.example.com/v1"
                                    className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">API Key</label>
                            <input
                                type="password"
                                value={chatbotApiKey}
                                onChange={(e) => setChatbotApiKey(e.target.value)}
                                placeholder={has_chatbot_api_key ? "Enter new API key to update..." : "Enter API key"}
                                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                            {has_chatbot_api_key && (
                                <p className="text-xs text-slate-500 mt-1">Leave blank to keep existing key</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Model</label>
                            <input
                                type="text"
                                value={chatbotModel || ''}
                                onChange={(e) => setChatbotModel(e.target.value)}
                                placeholder="gpt-4o-mini"
                                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">System Prompt</label>
                            <textarea
                                value={chatbotSystemPrompt || ''}
                                onChange={(e) => setChatbotSystemPrompt(e.target.value)}
                                rows={6}
                                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Temperature ({chatbotTemperature})
                                </label>
                                <input
                                    type="range"
                                    min="0"
                                    max="1"
                                    step="0.1"
                                    value={chatbotTemperature}
                                    onChange={(e) => setChatbotTemperature(parseFloat(e.target.value))}
                                    className="block w-full"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Max Tokens</label>
                                <input
                                    type="number"
                                    min="100"
                                    max="4000"
                                    value={chatbotMaxTokens}
                                    onChange={(e) => setChatbotMaxTokens(parseInt(e.target.value))}
                                    className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                        </div>

                        <div className="pt-4 flex justify-end">
                            <button
                                type="button"
                                onClick={saveChatbotSettings}
                                className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500"
                            >
                                Save Chatbot Settings
                            </button>
                        </div>
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-amber-200 p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-base font-semibold text-slate-900">Debug Mode</h3>
                            <p className="text-sm text-slate-500 mt-1">
                                When enabled, OTP values will be auto-filled on verification screens for testing purposes.
                            </p>
                            <p className="text-xs text-amber-600 font-medium mt-2">
                                This setting also exposes OTP values in page responses. Disable in production.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={debugOtp}
                            onClick={toggleDebugOtp}
                            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 ${debugOtp ? 'bg-amber-500' : 'bg-slate-300'}`}
                        >
                            <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${debugOtp ? 'translate-x-5' : 'translate-x-0'}`} />
                        </button>
                    </div>
                </div>
            </div>
            <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
        </AppLayout>
    );
}
