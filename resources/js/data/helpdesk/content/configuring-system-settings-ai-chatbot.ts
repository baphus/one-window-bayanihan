const content = `# Configuring System Settings and AI Chatbot

This guide covers the system settings available to administrators, including the AI chatbot configuration.

## Accessing System Settings

Navigate to **Admin > System Settings** to access the configuration panel.

## OTP Debug Mode

This setting controls whether OTP codes are auto-filled during development.

| Setting | Effect |
|---------|--------|
| Enabled | OTP field is auto-populated with a debug code — no real SMS/email sent |
| Disabled | Normal OTP behavior — real codes sent to user email |

> **Critical Warning:** Only enable OTP Debug Mode in local development environments. Never enable this in production. It bypasses all real OTP verification and creates a security vulnerability.

## Session Timeout Configuration

Controls how long a user session remains active without activity:

- **Default:** 120 minutes
- Adjust based on your security requirements
- Shorter timeouts (30-60 min) for sensitive environments
- Longer timeouts (up to 480 min) may be acceptable in controlled office settings

When a session expires, the user is automatically logged out and must re-authenticate.

## File Upload Limits

| Setting | Description | Recommended |
|---------|-------------|-------------|
| Max File Size | Largest single file allowed | 10MB |
| Allowed Types | Accepted file formats | PDF, JPG, PNG |

Files exceeding the size limit will be rejected with an error message. Ensure users are informed of these limits through the helpdesk documentation.

## AI Chatbot Configuration

The system includes an AI-powered chatbot that can answer user questions based on helpdesk articles.

### Provider Selection

Choose your AI provider:

- **OpenAI** — GPT-4 models, most capable but requires API key
- **Anthropic** — Claude models, strong on safety and reasoning
- **Custom** — connect to your own LLM endpoint

### API Key Management

- Enter the API key in the **masked input** field
- The key is stored encrypted in the database
- Once saved, the key is masked for security
- To change the key, enter a new value (the old one is overwritten)

### Chatbot Instruction Prompt

This **system message** defines how the chatbot behaves:

- Set the chatbot personality and tone
- Define what topics it can discuss
- Set boundaries on what it should not answer
- Instruct it to cite helpdesk articles as sources

Example prompt:

\`\`\`
You are a helpful assistant for the One Window Bayanihan 
system. Answer questions about case management, referrals, 
and system features. Use the helpdesk articles as your 
source of information. If you do not know the answer, 
say so and direct the user to contact their supervisor 
or system administrator.
\`\`\`

### Model Parameters

| Parameter | Purpose | Recommended |
|-----------|---------|-------------|
| Temperature | Controls randomness of responses (0.0-1.0) | 0.3 for factual answers |
| Token Limit | Maximum response length | 1024 tokens |

Lower temperature values (0.1-0.3) produce more focused, deterministic responses suitable for factual Q&A. Higher values (0.7-1.0) produce more creative responses.

## Testing Configuration Changes

After making configuration changes:

1. Click **"Save"** to apply changes
2. Test the affected feature
3. For OTP changes, log out and verify the login flow
4. For chatbot changes, open the chatbot and ask a test question
5. For upload limits, try uploading files of different sizes

## Configuration Best Practices

- Document all configuration changes in your internal change log
- Test changes in a staging environment first
- Set up monitoring alerts for critical settings changes
- Review all settings during quarterly system audits
- Keep API keys secure and rotate them periodically
`;
export default content;
