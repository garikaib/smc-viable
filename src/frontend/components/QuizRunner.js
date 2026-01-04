import { useState, useEffect } from '@wordpress/element';
import { Button, SelectControl, TextControl, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import './style.scss';

export default function QuizRunner({ quizId }) {
    const [quiz, setQuiz] = useState(null);
    const [loading, setLoading] = useState(true);
    const [answers, setAnswers] = useState({});
    const [isSubmitted, setIsSubmitted] = useState(false);
    const [score, setScore] = useState(0);

    useEffect(() => {
        apiFetch({ path: `/smc/v1/quizzes/${quizId}` })
            .then((data) => {
                setQuiz(data);
            })
            .catch((err) => console.error(err))
            .finally(() => setLoading(false));
    }, [quizId]);

    const handleAnswerChange = (questionIndex, value, optionScore = 0) => {
        setAnswers({
            ...answers,
            [questionIndex]: { value, score: optionScore }
        });
    };

    const handleSubmit = () => {
        let totalScore = 0;
        Object.values(answers).forEach((ans) => {
            totalScore += ans.score; // only dropdowns have scores passed here
        });
        setScore(totalScore);
        setIsSubmitted(true);
    };

    if (loading) return <Spinner />;
    if (!quiz) return <p>{__('Quiz not found.', 'smc-viable')}</p>;

    const questions = quiz.meta._smc_quiz_questions || [];

    if (isSubmitted) {
        return (
            <div className="smc-quiz-result">
                <h3>{__('Quiz Completed!', 'smc-viable')}</h3>
                <p>{__('Your Score:', 'smc-viable')} <strong>{score}</strong></p>
                <Button onClick={() => window.location.reload()}>{__('Retake Quiz', 'smc-viable')}</Button>
            </div>
        );
    }

    return (
        <div className="smc-quiz-runner">
            <h3>{quiz.title.rendered}</h3>
            {questions.map((q, index) => (
                <div key={index} className="smc-quiz-question">
                    <p><strong>{index + 1}. {q.text}</strong></p>
                    {q.type === 'dropdown' ? (
                        <SelectControl
                            label={__('Select an answer', 'smc-viable')}
                            value={answers[index]?.value || ''}
                            options={[
                                { label: __('Select...', 'smc-viable'), value: '' },
                                ...q.options.map(opt => ({
                                    label: opt.label,
                                    value: opt.label, // tracking by label for simplicity
                                    score: opt.score
                                }))
                            ]}
                            onChange={(val) => {
                                const selectedOpt = q.options.find(o => o.label === val);
                                handleAnswerChange(index, val, selectedOpt ? parseInt(selectedOpt.score) : 0);
                            }}
                        />
                    ) : (
                        <TextControl
                            label={__('Your Answer', 'smc-viable')}
                            value={answers[index]?.value || ''}
                            onChange={(val) => handleAnswerChange(index, val, 0)}
                        />
                    )}
                </div>
            ))}
            <div className="smc-quiz-submit">
                <Button isPrimary onClick={handleSubmit}>{__('Submit Quiz', 'smc-viable')}</Button>
            </div>
        </div>
    );
}
