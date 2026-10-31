import corpus from '../../../helpdesk/articles.json';
import type { HelpdeskArticle } from './types';

// Test fixtures only. Runtime pages receive authorized articles from the server.
export const articles: HelpdeskArticle[] = corpus;
