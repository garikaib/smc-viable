import { __ } from '@wordpress/i18n';
import { Button, TextControl, Spinner, Notice } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { fetchQuiz, saveQuiz } from '../utils/api';
import QuestionEditor from './QuestionEditor';

export default function QuizEditor({ quizId, onBack }) {
    const [title, setTitle] = useState('');
    const [questions, setQuestions] = useState([]);
    const [isLoading, setIsLoading] = useState(!!quizId);
    const [isSaving, setIsSaving] = useState(false);
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        if (quizId) {
            fetchQuiz(quizId)
                .then((data) => {
                    setTitle(data.title.rendered);
                    // Parse meta if exists, or default to empty
                    const metaQuestions = data.meta?._smc_quiz_questions || [];
                    // Ensure it's an array (meta strings need parsing if not using single:true/array schema)
                    // WP REST API handles JSON schema if registered correctly, otherwise it might be a string.
                    // Assuming for now it's returned as object because we will register it as boolean/object in PHP.
                    setQuestions(Array.isArray(metaQuestions) ? metaQuestions : []);
                })
                .catch((err) => setNotice({ status: 'error', text: err.message }))
                .finally(() => setIsLoading(false));
        }
    }, [quizId]);

    const handleSave = () => {
        setIsSaving(true);
        setNotice(null);

        const data = {
            id: quizId,
            title: title || __('New Quiz', 'smc-viable'),
            status: 'publish', // Force publish for now
            meta: {
                _smc_quiz_questions: questions,
            }
        };

        saveQuiz(data)
            .then(() => {
                setNotice({ status: 'success', text: __('Quiz saved successfully!', 'smc-viable') });
                if (!quizId) {
                    // better UX would be to redirect or switch mode, but for now simple notice
                    onBack();
                }
            })
            .catch((err) => setNotice({ status: 'error', text: err.message }))
            .finally(() => setIsSaving(false));
    };

    const addQuestion = () => {
        const newQ = { id: Date.now(), type: 'dropdown', text: '', options: [] };
        setQuestions([...questions, newQ]);
    };

    const updateQuestion = (index, newQ) => {
        const newQuestions = [...questions];
        newQuestions[index] = newQ;
        setQuestions(newQuestions);
    };

    const removeQuestion = (index) => {
        const newQuestions = questions.filter((_, i) => i !== index);
        setQuestions(newQuestions);
    };

    if (isLoading) return <Spinner />;

    return (
        <div className="smc-quiz-editor">
            <Button isLink onClick={onBack} style={{ marginBottom: '20px' }}>&larr; {__('Back to List', 'smc-viable')}</Button>

            <h2>{quizId ? __('Edit Quiz', 'smc-viable') : __('Create Quiz', 'smc-viable')}</h2>

            {notice && (
                <Notice status={notice.status} onRemove={() => setNotice(null)}>
                    {notice.text}
                </Notice>
            )}

            <TextControl
                label={__('Quiz Title', 'smc-viable')}
                value={title}
                onChange={setTitle}
            />

            <hr />

            <h3>{__('Questions', 'smc-viable')}</h3>
            {questions.map((q, index) => (
                <QuestionEditor
                    key={index}
                    question={q}
                    onChange={(newQ) => updateQuestion(index, newQ)}
                    onRemove={() => removeQuestion(index)}
                />
            ))}

            <Button isSecondary onClick={addQuestion} style={{ marginBottom: '20px' }}>{__('Add Question', 'smc-viable')}</Button>

            <hr />

            <Button isPrimary onClick={handleSave} isBusy={isSaving}>{__('Save Quiz', 'smc-viable')}</Button>
        </div>
    );
}
