import { useState, useEffect, useMemo } from '@wordpress/element';
import { Button, Spinner, TextControl, SelectControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import ScoreIndicator from './ScoreIndicator';
import ResultsDashboard from './ResultsDashboard';

import './style.scss';

export default function QuizRunner({ quizId }) {
    const [quiz, setQuiz] = useState(null);
    const [loading, setLoading] = useState(true);
    const [answers, setAnswers] = useState({});
    const [isSubmitted, setIsSubmitted] = useState(false);
    const [currentStageIndex, setCurrentStageIndex] = useState(0);

    useEffect(() => {
        if (!quizId) return;
        apiFetch({ path: `/smc/v1/quizzes/${quizId}` })
            .then((data) => {
                setQuiz(data);
            })
            .catch((err) => console.error(err))
            .finally(() => setLoading(false));
    }, [quizId]);

    // Group questions by stage
    const stages = useMemo(() => {
        if (!quiz) return [];
        const questions = quiz.meta._smc_quiz_questions || [];
        const groups = {};
        questions.forEach(q => {
            const stage = q.stage || 'Other';
            if (!groups[stage]) groups[stage] = [];
            groups[stage].push(q);
        });
        return Object.keys(groups).map(key => ({
            name: key,
            items: groups[key]
        }));
    }, [quiz]);

    const currentStage = stages[currentStageIndex];

    const handleAnswerChange = (questionId, value) => {
        // Here we just store the value. Score calculation logic will be done on submit or in a computed way.
        // For scorable items, value IS the score.
        setAnswers(prev => ({
            ...prev,
            [questionId]: value
        }));
    };

    const nextStage = () => {
        if (currentStageIndex < stages.length - 1) {
            setCurrentStageIndex(currentStageIndex + 1);
            window.scrollTo(0, 0);
        } else {
            handleSubmit();
        }
    };

    const prevStage = () => {
        if (currentStageIndex > 0) {
            setCurrentStageIndex(currentStageIndex - 1);
            window.scrollTo(0, 0);
        }
    };

    const handleSubmit = () => {
        setIsSubmitted(true);
        window.scrollTo(0, 0);
    };

    if (loading) return <div className="p-8 text-center"><Spinner /></div>;
    if (!quiz || !currentStage) return <p className="text-error">{__('Quiz not found or empty.', 'smc-viable')}</p>;



    // ... (inside QuizRunner)

    if (isSubmitted) {
        return <ResultsDashboard answers={answers} quiz={quiz} />;
    }

    return (
        <div className="smc-quiz-runner bg-base-100 p-6 rounded-lg shadow-sm">
            <h2 className="text-3xl font-bold mb-2 text-primary">{quiz.title.rendered}</h2>

            {/* Progress Bar */}
            <div className="mb-8">
                <div className="flex justify-between items-end mb-2">
                    <h3 className="text-xl font-bold">{currentStage.name}</h3>
                    <span className="text-xs text-base-content/60">{__('Stage', 'smc-viable')} {currentStageIndex + 1} / {stages.length}</span>
                </div>
                <progress
                    className="progress progress-primary w-full"
                    value={currentStageIndex + 1}
                    max={stages.length + 1}
                ></progress>
            </div>

            {/* Questions */}
            <div className="space-y-6">
                {currentStage.items.map((q, index) => (
                    <div key={q.id || index} className="card bg-base-100 border border-base-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex flex-col md:flex-row gap-6">

                            {/* Question Info */}
                            <div className="md:w-2/3">
                                {q.indicator && (
                                    <div className="badge badge-secondary badge-outline mb-2">{q.indicator}</div>
                                )}
                                <p className="font-medium text-lg leading-relaxed">{q.text || q.indicator}</p>
                                {q.key_text && q.type === 'scorable' && (
                                    <p className="text-sm text-base-content/60 mt-2 italic">{q.key_text}</p>
                                )}
                                {q.guidance && (
                                    <div className="mt-2 p-3 bg-base-200 rounded text-sm text-base-content/70">
                                        {q.guidance}
                                    </div>
                                )}
                            </div>

                            {/* Input Area */}
                            <div className="md:w-1/3 flex items-center justify-end">

                                {q.type === 'text' && (
                                    <TextControl
                                        value={answers[q.id] || ''}
                                        onChange={(val) => handleAnswerChange(q.id, val)}
                                        placeholder={__('Type here...', 'smc-viable')}
                                        className="w-full"
                                    />
                                )}

                                {q.type === 'select' && (
                                    <div className="w-full">
                                        <SelectControl
                                            value={answers[q.id] || ''}
                                            options={[
                                                { label: __('Pick one...', 'smc-viable'), value: '' },
                                                ...(q.options || []).map(opt => {
                                                    // Handle object or string options
                                                    const label = typeof opt === 'object' ? opt.label : opt;
                                                    const score = typeof opt === 'object' ? opt.score : 0;
                                                    // We store the label as value, but we can look up score later. 
                                                    // Or better: Use the label as the value, and the parent component calculates score.
                                                    // But for color coding, we need to know the score of the CURRENT value.
                                                    return { label: label, value: label };
                                                })
                                            ]}
                                            onChange={(val) => handleAnswerChange(q.id, val)}
                                            className={`w-full ${(() => {
                                                // Calculate color based on selected value's score
                                                const selectedOpt = (q.options || []).find(o => (typeof o === 'object' ? o.label : o) === answers[q.id]);
                                                if (!selectedOpt) return '';
                                                const score = typeof selectedOpt === 'object' ? selectedOpt.score : 0;

                                                if (score === 15) return 'select-success text-success-content';
                                                if (score === 10) return 'select-warning'; // Yellowish
                                                if (score === 5) return 'select-warning text-warning-content'; // Specific color adjustment if needed
                                                if (score === -5) return 'select-error text-error-content bg-error/10';
                                                return '';
                                            })()}`}
                                        />
                                        {/* Status Indicator Badge for selected item */}
                                        {answers[q.id] && (() => {
                                            const selectedOpt = (q.options || []).find(o => (typeof o === 'object' ? o.label : o) === answers[q.id]);
                                            if (!selectedOpt || typeof selectedOpt !== 'object' || !selectedOpt.score) return null;

                                            const { score } = selectedOpt;
                                            let badgeClass = 'badge-ghost';
                                            let label = '';

                                            if (score === 15) { badgeClass = 'badge-success'; label = 'Great'; }
                                            else if (score === 10) { badgeClass = 'badge-warning'; label = 'Good'; }
                                            else if (score === 5) { badgeClass = 'badge-warning'; label = 'Borderline'; }
                                            else if (score === -5) { badgeClass = 'badge-error'; label = 'Flag'; }

                                            if (!label) return null;

                                            return (
                                                <div className={`mt-1 badge ${badgeClass} badge-outline text-xs`}>
                                                    {label} ({score})
                                                </div>
                                            );
                                        })()}
                                    </div>
                                )}

                                {q.type === 'date' && (
                                    <input
                                        type="month"
                                        className="input input-bordered w-full"
                                        value={answers[q.id] || ''}
                                        onChange={(e) => handleAnswerChange(q.id, e.target.value)}
                                    />
                                )}

                                {q.type === 'scorable' && (
                                    <ScoreIndicator
                                        value={answers[q.id]}
                                        onChange={(val) => handleAnswerChange(q.id, val)}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Navigation */}
            <div className="flex justify-between mt-8 pt-4 border-t border-base-200">
                <Button
                    className="btn btn-ghost"
                    onClick={prevStage}
                    disabled={currentStageIndex === 0}
                >
                    {__('Back', 'smc-viable')}
                </Button>
                <Button
                    className="btn btn-secondary text-white"
                    onClick={nextStage}
                >
                    {currentStageIndex === stages.length - 1 ? __('Finish & Review', 'smc-viable') : __('Next Stage', 'smc-viable')}
                </Button>
            </div>

        </div>
    );
}
