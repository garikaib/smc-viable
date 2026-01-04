import { useState, useEffect, useMemo } from '@wordpress/element';
import { Button, Spinner, TextControl } from '@wordpress/components';
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
        const questions = quiz.meta?._smc_quiz_questions || [];
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

    if (isSubmitted) {
        return <ResultsDashboard answers={answers} quiz={quiz} />;
    }

    return (
        <div className="smc-quiz-runner bg-base-100 p-4 md:p-8 rounded-lg shadow-sm max-w-4xl mx-auto">
            <h2 className="text-2xl md:text-3xl font-bold mb-6 md:mb-8 text-primary border-b pb-4">{quiz.title.rendered}</h2>

            {/* Progress Bar */}
            <div className="mb-8 md:mb-10">
                <div className="flex justify-between items-end mb-2">
                    <h3 className="text-lg md:text-xl font-bold">{currentStage.name}</h3>
                    <span className="text-xs text-base-content/60">{__('Stage', 'smc-viable')} {currentStageIndex + 1} / {stages.length}</span>
                </div>
                <progress
                    className="progress progress-primary w-full"
                    value={currentStageIndex + 1}
                    max={stages.length + 1}
                ></progress>
            </div>

            {/* Questions */}
            <div className="space-y-6 md:space-y-8">
                {currentStage.items.map((q, index) => (
                    <div key={q.id || index} className="card bg-base-100 border border-base-200 p-5 md:p-8 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex flex-col gap-4 md:gap-6">

                            {/* Question Info */}
                            <div className="w-full">
                                {q.indicator && (
                                    <div className="badge badge-secondary badge-outline mb-2 md:mb-3 p-2 md:p-3 text-xs md:text-sm">{q.indicator}</div>
                                )}
                                <p className="font-bold text-lg md:text-xl leading-relaxed text-base-content">{q.text || q.indicator}</p>
                                {q.key_text && q.type === 'scorable' && (
                                    <p className="text-xs md:text-sm text-base-content/60 mt-2 italic">{q.key_text}</p>
                                )}
                                {q.guidance && (
                                    <div className="mt-3 p-3 md:p-4 bg-base-200 rounded-lg text-sm text-base-content/80 leading-relaxed border-l-4 border-primary">
                                        {q.guidance}
                                    </div>
                                )}
                            </div>

                            {/* Input Area */}
                            <div className="w-full mt-1 md:mt-2">
                                {/* TEXT INPUT */}
                                {q.type === 'text' && (
                                    <TextControl
                                        value={answers[q.id] || ''}
                                        onChange={(val) => handleAnswerChange(q.id, val)}
                                        placeholder={__('Type here...', 'smc-viable')}
                                        className="w-full text-base md:text-lg"
                                        style={{ lineHeight: '1.5', padding: '10px' }}
                                    />
                                )}

                                {/* OPTIONS (Radio Style) - Prioritize if options exist */}
                                {((q.type === 'select' || q.type === 'scorable') && q.options && q.options.length > 0) && (
                                    <div className="flex flex-col gap-2 md:gap-3">
                                        {q.options.map((opt, idx) => {
                                            const label = typeof opt === 'object' ? opt.label : opt;
                                            const value = label; // Use label as value
                                            const isSelected = answers[q.id] === value;

                                            // Determine styles based on selection
                                            let containerClass = "relative flex items-center p-3 md:p-4 rounded-lg border-2 cursor-pointer transition-all duration-200 group";
                                            if (isSelected) {
                                                containerClass += " border-primary bg-primary/5 ring-1 ring-primary";
                                            } else {
                                                containerClass += " border-base-200 hover:border-primary/50 hover:bg-base-50";
                                            }

                                            return (
                                                <label key={idx} className={containerClass}>
                                                    <input
                                                        type="radio"
                                                        name={`question_${q.id}`}
                                                        value={value}
                                                        checked={isSelected}
                                                        onChange={() => handleAnswerChange(q.id, value)}
                                                        className="radio radio-sm md:radio-md radio-primary mr-3 md:mr-4"
                                                    />
                                                    <span className={`text-base md:text-lg font-medium flex-grow ${isSelected ? 'text-primary' : 'text-base-content'}`}>
                                                        {label}
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                )}

                                {/* DATE INPUT */}
                                {q.type === 'date' && (
                                    <input
                                        type="month"
                                        className="input input-bordered w-full max-w-sm"
                                        value={answers[q.id] || ''}
                                        onChange={(e) => handleAnswerChange(q.id, e.target.value)}
                                    />
                                )}

                                {/* FALLBACK SCORABLE (if no options) */}
                                {q.type === 'scorable' && (!q.options || q.options.length === 0) && (
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
            <div className="flex justify-between mt-8 md:mt-10 pt-4 md:pt-6 border-t border-base-200">
                <Button
                    className="btn btn-ghost"
                    onClick={prevStage}
                    disabled={currentStageIndex === 0}
                >
                    {__('Back', 'smc-viable')}
                </Button>
                <Button
                    className="btn btn-secondary text-white btn-wide"
                    onClick={nextStage}
                >
                    {currentStageIndex === stages.length - 1 ? __('Finish & Review', 'smc-viable') : __('Next Stage', 'smc-viable')}
                </Button>
            </div>

        </div>
    );
}
