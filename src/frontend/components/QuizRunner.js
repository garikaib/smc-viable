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

    // Persistence: Load answers on mount
    useEffect(() => {
        if (!quizId) return;
        const saved = localStorage.getItem(`smc_quiz_answers_${quizId}`);
        if (saved) {
            try {
                setAnswers(JSON.parse(saved));
            } catch (e) {
                console.error("Failed to load saved answers", e);
            }
        }
    }, [quizId]);

    // Persistence: Save answers on change
    useEffect(() => {
        if (quizId && Object.keys(answers).length > 0) {
            localStorage.setItem(`smc_quiz_answers_${quizId}`, JSON.stringify(answers));
        }
    }, [answers, quizId]);

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

    // Clear persistence on submit
    const handleSubmit = () => {
        setIsSubmitted(true);
        localStorage.removeItem(`smc_quiz_answers_${quizId}`);
        window.scrollTo(0, 0);
    };

    if (loading) return <div className="p-12 text-center text-gray-500"><Spinner /></div>;
    if (!quiz || !currentStage) return <p className="text-error">{__('Quiz not found or empty.', 'smc-viable')}</p>;

    if (isSubmitted) {
        return <ResultsDashboard answers={answers} quiz={quiz} />;
    }

    return (
        <div className="smc-quiz-runner bg-white p-6 md:p-10 rounded-xl shadow-lg border border-gray-100 max-w-4xl mx-auto transition-all duration-300">
            <h2 className="text-2xl md:text-3xl font-extrabold mb-6 md:mb-8 text-gray-800 border-b border-gray-100 pb-4 tracking-tight">{quiz.title.rendered}</h2>

            {/* Progress Bar */}
            <div className="mb-8 md:mb-12">
                <div className="flex justify-between items-end mb-3">
                    <h3 className="text-lg md:text-xl font-bold text-gray-700">{currentStage.name}</h3>
                    <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">{__('Stage', 'smc-viable')} {currentStageIndex + 1} / {stages.length}</span>
                </div>
                <progress
                    className="progress progress-primary w-full h-3"
                    value={currentStageIndex + 1}
                    max={stages.length + 1}
                ></progress>
            </div>

            {/* Questions - Animated Keyed Wrapper */}
            <div key={currentStageIndex} className="space-y-8 animate-fade-in">
                {currentStage.items.map((q, index) => (
                    <div key={q.id || index} className="card bg-white border border-gray-200 p-6 md:p-8 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                        <div className="flex flex-col gap-5 md:gap-6">

                            {/* Question Info */}
                            <div className="w-full">
                                {q.indicator && (
                                    <div className="inline-block bg-gray-100 text-gray-600 rounded-full px-3 py-1 text-xs font-semibold tracking-wide mb-3">{q.indicator}</div>
                                )}
                                <p className="font-bold text-lg md:text-xl leading-snug text-gray-800">{q.text || q.indicator}</p>
                                {q.key_text && q.type === 'scorable' && (
                                    <p className="text-xs md:text-sm text-gray-500 mt-2 italic">{q.key_text}</p>
                                )}
                                {q.guidance && (
                                    <div className="mt-4 p-4 bg-blue-50 rounded-lg text-sm text-blue-800 leading-relaxed border-l-4 border-blue-500">
                                        {q.guidance}
                                    </div>
                                )}
                            </div>

                            {/* Input Area */}
                            <div className="w-full mt-1">
                                {/* TEXT INPUT */}
                                {q.type === 'text' && (
                                    <TextControl
                                        value={answers[q.id] || ''}
                                        onChange={(val) => handleAnswerChange(q.id, val)}
                                        placeholder={__('Type here...', 'smc-viable')}
                                        className="w-full text-base"
                                        style={{ lineHeight: '1.5', padding: '12px' }}
                                    />
                                )}

                                {/* OPTIONS (Radio Style) - Refined */}
                                {((q.type === 'select' || q.type === 'scorable') && q.options && q.options.length > 0) && (
                                    <div className="flex flex-col gap-2">
                                        {q.options.map((opt, idx) => {
                                            const label = typeof opt === 'object' ? opt.label : opt;
                                            const value = label;
                                            const isSelected = answers[q.id] === value;

                                            // Determine styles based on selection
                                            let containerClass = "relative flex items-center p-3 md:p-4 rounded-lg border cursor-pointer transition-all duration-200 group";
                                            if (isSelected) {
                                                containerClass += " border-primary bg-primary/5 shadow-sm ring-1 ring-primary";
                                            } else {
                                                containerClass += " border-gray-200 hover:border-blue-400 hover:bg-gray-50";
                                            }

                                            return (
                                                <label key={idx} className={containerClass}>
                                                    <input
                                                        type="radio"
                                                        name={`question_${q.id}`}
                                                        value={value}
                                                        checked={isSelected}
                                                        onChange={() => handleAnswerChange(q.id, value)}
                                                        className="radio radio-sm radio-primary mr-3 md:mr-4 border-gray-300"
                                                    />
                                                    <span className={`text-sm md:text-base font-medium flex-grow ${isSelected ? 'text-primary' : 'text-gray-700'}`}>
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

                                {/* FALLBACK SCORABLE */}
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
            <div className="flex justify-between mt-10 pt-6 border-t border-base-200">
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
