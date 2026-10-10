import { shallowRef, toRaw } from 'vue';

/**
 * Shared by the Question Bank and Exam Excel import review pages.
 */
export const normalizeImportRow = (question) => ({
  ...question,
  subject_id: question.subject_id ? Number(question.subject_id) : '',
  question_category_id: question.question_category_id ? Number(question.question_category_id) : '',
  class_level_ids: (question.class_level_ids || []).map(Number),
  correct_options: question.correct_options || [],
  is_active: question.is_active === undefined ? true : Boolean(question.is_active),
});

const required = (value) => String(value ?? '').trim() !== '';

export const categoriesForRow = (categories, question) => (categories || [])
  .filter((category) => Number(category.subject_id) === Number(question.subject_id));

/**
 * Client-side mirror of QuestionBulkImportService::questionErrors().
 */
export function questionRowErrors(question, categories) {
  const errors = [];
  if (!question.subject_id) errors.push('Choose a subject');
  if (!question.class_level_ids.length) errors.push('Choose at least one class level');
  if (question.question_category_id && !categoriesForRow(categories, question).some((category) => Number(category.id) === Number(question.question_category_id))) errors.push('Category must belong to the subject');
  if (!['easy', 'medium', 'hard'].includes(question.difficulty)) errors.push('Choose a valid difficulty');
  if (!['single', 'multiple'].includes(question.question_type)) errors.push('Choose a valid question type');
  if (!required(question.question_text)) errors.push('Question text is required');
  for (const option of ['a', 'b', 'c', 'd']) if (!required(question[`option_${option}`])) errors.push(`Option ${option.toUpperCase()} is required`);
  if (!question.correct_options.length) errors.push('Choose the correct option');
  if (question.question_type === 'single' && question.correct_options.length !== 1) errors.push('Single-correct questions need one answer');
  if (!Number.isInteger(Number(question.marks)) || Number(question.marks) < 1 || Number(question.marks) > 10) errors.push('Marks must be between 1 and 10');
  if (required(question.negative_marks) && (Number.isNaN(Number(question.negative_marks)) || Number(question.negative_marks) < 0 || Number(question.negative_marks) > 5)) errors.push('Negative marks must be between 0 and 5');
  return errors;
}

/**
 * Server errors are pinned to the row object plus a snapshot of it, so they survive row removal
 * and disappear as soon as that row is edited (otherwise they would block re-submission forever).
 */
export function useServerRowErrors() {
  const pinned = shallowRef(new WeakMap());
  const snapshot = (question) => JSON.stringify(question);

  const errorsFor = (question) => {
    const entry = pinned.value.get(toRaw(question));
    return entry && entry.snapshot === snapshot(question) ? entry.messages : [];
  };

  const pin = (errors, questions) => {
    const next = new WeakMap();
    questions.forEach((question, index) => {
      const messages = Object.entries(errors)
        .filter(([key]) => key.startsWith(`questions.${index}.`))
        .map(([, message]) => message);
      if (messages.length) next.set(toRaw(question), { snapshot: snapshot(question), messages });
    });
    pinned.value = next;
  };

  return { errorsFor, pin };
}
