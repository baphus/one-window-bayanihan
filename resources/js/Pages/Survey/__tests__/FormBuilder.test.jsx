import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import FormBuilder from '../FormBuilder';

const state = vi.hoisted(() => ({ form: null, route: vi.fn((name, id) => `/${name}/${id ?? ''}`) }));

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
  useForm: (initial) => {
    if (!state.form) {
      state.form = {
        data: initial,
        errors: {},
        processing: false,
        setData: (key, value) => { state.form.data[key] = value; },
        transform: (callback) => { state.form.transformCallback = callback; },
        post: vi.fn((url) => {
          state.form.submitted = { method: 'post', url, data: state.form.transformCallback(state.form.data) };
        }),
        patch: vi.fn((url) => {
          state.form.submitted = { method: 'patch', url, data: state.form.transformCallback(state.form.data) };
        }),
      };
    }
    return state.form;
  },
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null }) }));
vi.mock('@/Components/InputError', () => ({ default: () => null }));

globalThis.route = state.route;

const form = {
  title: 'Feedback',
  description: '',
  questions: [
    { type: 'text', label: 'First', options: [], is_required: true, order: 0 },
    { type: 'rating', label: 'Second', options: [], is_required: true, order: 1 },
  ],
};

describe('FormBuilder', () => {
  it('submits the current question order for a new form', () => {
    state.form = null;
    const view = render(<FormBuilder form={null} questionTypes={['text', 'rating']} likertLabels={{}} />);
    fireEvent.click(screen.getByRole('button', { name: '+ Text' }));
    view.rerender(<FormBuilder form={null} questionTypes={['text', 'rating']} likertLabels={{}} />);
    fireEvent.click(screen.getByRole('button', { name: '+ Rating' }));
    view.rerender(<FormBuilder form={null} questionTypes={['text', 'rating']} likertLabels={{}} />);
    const cards = screen.getAllByRole('article');
    fireEvent.change(within(cards[0]).getByRole('textbox'), { target: { value: 'First' } });
    fireEvent.change(within(cards[1]).getByRole('textbox'), { target: { value: 'Second' } });
    fireEvent.click(within(cards[0]).getByRole('button', { name: '↓ Move down' }));
    fireEvent.submit(screen.getByRole('button', { name: 'Save Form' }).closest('form'));

    expect(state.form.submitted.data.questions.map((question) => question.label)).toEqual(['Second', 'First']);
    expect(state.form.submitted.data.questions.map((question) => question.order)).toEqual([0, 1]);
    expect(state.form.post).toHaveBeenCalledWith('/survey.forms.store/');
  });

  it('uses the ordered payload when updating an existing form', () => {
    state.form = null;
    render(<FormBuilder form={{ ...form, id: 'form-1' }} questionTypes={['text', 'rating']} likertLabels={{}} />);
    const cards = screen.getAllByRole('article');
    fireEvent.click(within(cards[1]).getByRole('button', { name: '↑ Move up' }));
    fireEvent.submit(screen.getByRole('button', { name: 'Update Form' }).closest('form'));

    expect(state.form.submitted.data.questions.map((question) => question.label)).toEqual(['Second', 'First']);
    expect(state.form.patch).toHaveBeenCalledWith('/survey.forms.update/form-1');
  });

  it('submits the FormBuilder shape for every supported question type', () => {
    state.form = null;
    render(<FormBuilder form={{
      title: 'All question types',
      description: 'Description',
      questions: [
        { type: 'likert', label: 'Likert', options: [], is_required: true, order: 0 },
        { type: 'text', label: 'Text', options: [], is_required: false, order: 1 },
        { type: 'radio', label: 'Radio', options: ['Yes', 'No'], is_required: true, order: 2 },
        { type: 'checkbox', label: 'Checkbox', options: ['One', 'Two'], is_required: false, order: 3 },
        { type: 'rating', label: 'Rating', options: [], is_required: true, order: 4 },
      ],
    }} questionTypes={['likert', 'text', 'radio', 'checkbox', 'rating']} likertLabels={{}} />);

    fireEvent.submit(screen.getByRole('button', { name: 'Update Form' }).closest('form'));

    expect(state.form.submitted.data).toEqual({
      title: 'All question types',
      description: 'Description',
      questions: [
        { type: 'likert', label: 'Likert', options: [], is_required: true, order: 0 },
        { type: 'text', label: 'Text', options: [], is_required: false, order: 1 },
        { type: 'radio', label: 'Radio', options: ['Yes', 'No'], is_required: true, order: 2 },
        { type: 'checkbox', label: 'Checkbox', options: ['One', 'Two'], is_required: false, order: 3 },
        { type: 'rating', label: 'Rating', options: [], is_required: true, order: 4 },
      ],
    });
  });
});
