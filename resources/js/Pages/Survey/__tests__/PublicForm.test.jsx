import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PublicForm from '../PublicForm';

const state = vi.hoisted(() => ({
  props: { flash: {} },
  form: null,
}));

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }) => <title>{title}</title>,
  usePage: () => ({ props: state.props }),
  useForm: (initial) => {
    if (!state.form) {
      state.form = {
        data: initial,
        errors: {},
        processing: false,
        setData: (key, value) => {
          state.form.data[key] = value;
        },
        post: vi.fn(),
      };
    }
    return state.form;
  },
}));

vi.mock('@/Components/InputError', () => ({ default: ({ message }) => message ? <p>{message}</p> : null }));

const questions = [
  { id: 'q1', type: 'radio', label: 'Preferred channel?', options: ['Email', 'Phone'], is_required: true },
  { id: 'q2', type: 'radio', label: 'Preferred time?', options: ['Morning', 'Afternoon'], is_required: true },
  { id: 'q3', type: 'text', label: 'Comments', is_required: false },
  { id: 'q4', type: 'checkbox', label: 'Which updates would help?', options: ['Email', 'SMS'], is_required: false },
];

const renderForm = () => render(
  <PublicForm
    invitation={{ client_name: 'Juan Cruz', service_name: 'Assistance' }}
    surveyForm={{ title: 'Client Survey', description: 'Tell us what you think.' }}
    questions={questions}
  />,
);

describe('PublicForm', () => {
  it('builds answer payloads and posts to the current public URL', () => {
    state.form = null;
    state.props.flash = {};
    window.history.pushState({}, '', '/survey/token');
    renderForm();

    fireEvent.click(screen.getByRole('radio', { name: 'Email' }));
    fireEvent.click(screen.getByLabelText('Afternoon'));
    fireEvent.change(screen.getByPlaceholderText('Type your answer here...'), { target: { value: 'Helpful service' } });
    fireEvent.click(screen.getByLabelText('SMS'));
    fireEvent.submit(screen.getByRole('button', { name: 'Submit Survey' }).closest('form'));

    expect(state.form.data.answers).toEqual([
      { question_id: 'q1', answer: 'Email', selected_options: null },
      { question_id: 'q2', answer: 'Afternoon', selected_options: null },
      { question_id: 'q3', answer: 'Helpful service', selected_options: null },
      { question_id: 'q4', answer: null, selected_options: ['SMS'] },
    ]);
    expect(window.location.pathname).toBe('/survey/token');
    expect(state.form.post).toHaveBeenCalledWith('/survey/token');
  });

  it('keeps radio choices independent between questions', () => {
    state.form = null;
    state.props.flash = {};
    const view = renderForm();

    fireEvent.click(screen.getByRole('radio', { name: 'Email' }));
    view.rerender(<PublicForm invitation={{ client_name: 'Juan Cruz', service_name: 'Assistance' }} surveyForm={{ title: 'Client Survey' }} questions={questions} />);
    fireEvent.click(screen.getByLabelText('Morning'));

    expect(screen.getByRole('radio', { name: 'Email' })).toHaveAttribute('name', 'radio-q1');
    expect(screen.getByRole('radio', { name: 'Morning' })).toHaveAttribute('name', 'radio-q2');
    expect(state.form.data.answers.slice(0, 2).map((answer) => answer.answer)).toEqual(['Email', 'Morning']);
  });

  it('renders server validation errors', () => {
    state.form = null;
    state.props.flash = {};
    const view = renderForm();
    state.form.errors = { 'answers.0.answer': 'Choose an answer.' };
    view.rerender(<PublicForm invitation={{ client_name: 'Juan Cruz', service_name: 'Assistance' }} surveyForm={{ title: 'Client Survey' }} questions={questions} />);
    expect(screen.getByText('Choose an answer.')).toBeInTheDocument();
  });

  it('renders the success state from the flash message', () => {
    state.form = null;
    state.props.flash = { success: 'Your response was recorded.' };
    renderForm();
    expect(screen.getByText('Thank You!')).toBeInTheDocument();
    expect(screen.getByText('Your response was recorded.')).toBeInTheDocument();
  });
});
