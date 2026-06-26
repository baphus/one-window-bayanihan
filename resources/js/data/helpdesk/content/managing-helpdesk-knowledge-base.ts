const content = `# Managing the Helpdesk Knowledge Base

The helpdesk knowledge base provides self-service support content for all system users. This guide covers how to create, organize, and maintain articles.

## Accessing the Helpdesk CMS

Navigate to **Admin > Helpdesk** to access the content management system. The CMS has three main sections:

- **Articles** — the content items themselves
- **Categories** — organizational hierarchy
- **Tags** — cross-referencing keywords

## Creating a New Article

1. Click **"New Article"**
2. Enter the **title** — a clear, descriptive headline
3. Write the content in the **markdown editor**
4. The editor includes a **live preview** panel showing how the article will look
5. Assign a **category** from the dropdown
6. Select relevant **tags** for cross-referencing
7. Set **visibility** options:
   - **Public** — anyone can view (default)
   - **Authenticated** — logged-in users only
   - **Role Restricted** — specific roles only
8. Optionally mark as **featured** to highlight it on the helpdesk homepage
9. Click **"Save as Draft"** to continue editing later, or **"Publish"** to make it live

## Writing Effective Helpdesk Articles

### Structure

- Start with a **heading** that matches the article title
- Use **subheadings** to organize content into sections
- Include a **brief summary** at the beginning
- Use **numbered steps** for procedures
- Use **bullet lists** for items and options
- Use **tables** for structured data

### Style

- Write in **plain language** accessible to non-technical users
- Use **bold text** for UI elements and buttons
- Use \`code formatting\` for field names, file paths, and commands
- Keep paragraphs short and scannable
- Add **tips and warnings** in callout boxes

## Organizing with Categories and Tags

### Categories
Categories create the main navigation structure. Articles should be placed in the most specific category available. A category can have subcategories for more precise organization.

### Tags
Tags provide cross-referencing across categories. Add multiple tags to each article so users can find related content. Common tags include: \`cases\`, \`referrals\`, \`tracking\`, \`compliance\`, \`training\`.

## Previewing and Publishing Workflow

1. **Draft** — create and edit the article
2. **Preview** — use the live preview to check formatting
3. **Publish** — make the article visible to users
4. **Update** — edit published articles as needed (creates a revision)

## Managing Revisions

Every time an article is saved, a **revision** is created:

- Revisions preserve the complete article content at that point
- You can **roll back** to any previous revision
- Each revision shows **who made the change** and **when**
- Revisions include **edit notes** describing what changed

To roll back:
1. Open the article
2. Go to the **"Revisions"** tab
3. Find the revision you want to restore
4. Click **"Restore"** — the current content is replaced

## Viewing Article Feedback

Users can rate articles as helpful or not helpful:

1. Open the article in the CMS
2. View the **feedback count** (helpful vs unhelpful)
3. Review detailed comments if users have left them
4. Use feedback to identify articles that need improvement

## Maintenance Best Practices

- Review all articles **quarterly** for accuracy
- Update articles when system features change
- Archive outdated articles instead of deleting them
- Monitor feedback to identify gaps in coverage
- Cross-link related articles for easy navigation
`;

export default content;
