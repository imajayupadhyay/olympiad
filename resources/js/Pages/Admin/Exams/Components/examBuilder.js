let keySeed = 0;

export const sectionKey = () => `section-${Date.now()}-${keySeed++}`;

export const blankSection = (subjectId, number) => ({
  key: sectionKey(),
  id: null,
  name: `Section ${number}`,
  subject_id: subjectId || '',
  instructions: '',
  marks_per_question: '',
  negative_marks_per_question: '',
  question_ids: [],
});

export const hydrateSections = (sections = []) => sections.map((section) => ({
  key: sectionKey(),
  id: section.id,
  name: section.name,
  subject_id: section.subject_id,
  instructions: section.instructions || '',
  marks_per_question: section.marks_per_question ?? '',
  negative_marks_per_question: section.negative_marks_per_question ?? '',
  question_ids: (section.questions || []).map((question) => question.id),
}));

const filled = (value) => value !== '' && value !== null && value !== undefined;

/**
 * Mirrors ExamSectionService::pivotMarks — section override, then exam policy, then the question.
 */
export const marksFor = (exam, section, question) => {
  if (filled(section?.marks_per_question)) return Number(section.marks_per_question);
  return Number(exam.scoring_mode === 'uniform' ? exam.marks_per_question : question?.marks ?? 1);
};

export const negativeMarksFor = (exam, section, question) => {
  if (!exam.negative_marking_enabled) return 0;
  if (filled(section?.negative_marks_per_question)) return Number(section.negative_marks_per_question);
  return Number(exam.scoring_mode === 'uniform' ? exam.negative_marks_per_question : question?.negative_marks ?? 0);
};

export const stripHtml = (html) => {
  const element = document.createElement('div');
  element.innerHTML = html || '';
  return (element.textContent || element.innerText || '').trim();
};

export const difficultyClass = (difficulty) => ({
  easy: 'bg-green-100 text-green-700',
  medium: 'bg-amber-100 text-amber-700',
  hard: 'bg-red-100 text-red-700',
}[difficulty] || 'bg-gray-100 text-gray-600');

export const formatMarks = (value) => Number(value || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
