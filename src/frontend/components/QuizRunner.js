import { useState, useEffect, useMemo, useRef } from '@wordpress/element';
import { Button, Spinner, TextControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { ArrowRight, ArrowLeft, CheckCircle2 } from 'lucide-react';
import gsap from 'gsap';
import ResultsDashboard from './ResultsDashboard';

import './style.scss';

const normalizeQuestionType = (type) => {
    if (type === 'select' || type === 'scorable') return 'single_choice';
    if (type === 'text') return 'short_text';
    if (type === 'date') return 'date_month';
    return type || 'short_text';
};

export default function QuizRunner({ quizId }) {
    const [quiz, setQuiz] = useState(null);
    const [loading, setLoading] = useState(true);
    const [answers, setAnswers] = useState({});
    const [isSubmitted, setIsSubmitted] = useState(false);
    const [currentStageIndex, setCurrentStageIndex] = useState(0);

    // Refs for animation
    const heroRef = useRef(null);
    const formRef = useRef(null);
    const stageRef = useRef(null);

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

    // Data Fetching: Load Quiz Questions
    useEffect(() => {
        if (!quizId) return;
        setLoading(true);
        apiFetch({ path: `/smc/v1/quizzes/${quizId}` })
            .then((data) => {
                setQuiz(data);
                // Initial Animation Trigger
                setTimeout(() => {
                    if (heroRef.current && formRef.current) {
                        gsap.fromTo(heroRef.current,
                            { opacity: 0, y: 50 },
                            { opacity: 1, y: 0, duration: 1, ease: "power3.out" }
                        );
                        gsap.fromTo(formRef.current,
                            { opacity: 0, y: 80 },
                            { opacity: 1, y: 0, duration: 1, delay: 0.3, ease: "power2.out" }
                        );
                    }
                }, 100);
            })
            .catch((err) => console.error(err))
            .finally(() => setLoading(false));
    }, [quizId]);

    // Animate stage transitions
    useEffect(() => {
        if (stageRef.current) {
            gsap.fromTo(stageRef.current,
                { opacity: 0, x: 20 },
                { opacity: 1, x: 0, duration: 0.5, ease: "power2.out" }
            );
        }
    }, [currentStageIndex]);

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

    const handleObjectAnswerChange = (questionId, key, value) => {
        setAnswers(prev => ({
            ...prev,
            [questionId]: {
                ...(typeof prev[questionId] === 'object' && prev[questionId] ? prev[questionId] : {}),
                [key]: value
            }
        }));
    };

    const nextStage = () => {
        if (currentStageIndex < stages.length - 1) {
            setCurrentStageIndex(currentStageIndex + 1);
            window.scrollTo({ top: 500, behavior: 'smooth' }); // Scroll to form top, not hero
        } else {
            handleSubmit();
        }
    };

    const prevStage = () => {
        if (currentStageIndex > 0) {
            setCurrentStageIndex(currentStageIndex - 1);
            window.scrollTo({ top: 500, behavior: 'smooth' });
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

    // Extract Hero Data from Meta
    const heroTitle = quiz.meta?._smc_quiz_hero_title || quiz.title.rendered;

    // Subtitle / Intro Paragraph Logic
    // 1. Meta field (specific override)
    // 2. Excerpt (WordPress standard for summaries)
    // 3. Fallback to generic marketing copy if both are empty
    const heroSubtitle = quiz.meta?._smc_quiz_hero_subtitle ||
        (quiz.excerpt?.rendered ? quiz.excerpt.rendered.replace(/<[^>]+>/g, '') : '') ||
        __('Measure your business against world-class standards. Discover your strengths and identify areas for growth in just a few minutes.', 'smc-viable');

    return (
        <div className="smc-quiz-wrapper w-full">
            {/* PREMIUM HERO SECTION */}
            <div ref={heroRef} className="smc-quiz-hero-shell smc-quiz-hero flex flex-col justify-center px-6 py-6 md:py-8">
                <div className="smc-quiz-hero-overlay absolute inset-0 z-0"></div>

                {/* Content Container - Centered */}
                <div className="smc-quiz-hero-inner relative z-10 w-full flex flex-col items-center gap-6 max-w-7xl mx-auto">

                    {/* TOP: Badge */}
                    <div className="smc-quiz-hero-top w-full text-center" data-aos="fade-down">
                        <span className="smc-quiz-hero-badge inline-block px-5 py-2 rounded-full border border-white/20 bg-white/10 text-white text-xs font-bold uppercase tracking-widest backdrop-blur-md">
                            {__('Business Science', 'smc-viable')}
                        </span>
                    </div>

                    {/* BOTTOM: Title & Text */}
                    <div className="smc-quiz-hero-bottom w-full text-center max-w-4xl mx-auto" data-aos="fade-up">
                        <h1 className="smc-quiz-hero-title text-5xl md:text-7xl font-extrabold text-white mb-6 leading-tight drop-shadow-lg">
                            {heroTitle}
                        </h1>
                        {heroSubtitle && (
                            <p className="smc-quiz-hero-excerpt text-lg md:text-xl text-white/90 max-w-3xl mx-auto leading-relaxed drop-shadow-md">
                                {heroSubtitle}
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {/* FORM CONTAINER */}
            <div ref={formRef} className="smc-quiz-form-shell smc-quiz-form-wrap -mt-24 relative z-20 px-4 pb-20">
                <div className="smc-quiz-runner max-w-4xl mx-auto bg-base-100 rounded-3xl shadow-2xl shadow-black/20 p-8 md:p-12 border border-base-200">

                    {/* Progress Bar */}
                    <div className="mb-10">
                        <div className="flex justify-between items-end mb-4">
                            <h3 className="text-xl font-bold text-base-content/90">{currentStage.name}</h3>
                            <span className="text-xs font-bold text-base-content/50 uppercase tracking-widest">{__('Stage', 'smc-viable')} {currentStageIndex + 1} / {stages.length}</span>
                        </div>
                        <div className="w-full h-2 bg-base-200 rounded-full overflow-hidden">
                            <div
                                className="h-full bg-gradient-to-r from-red-700 via-yellow-500 to-teal-600 transition-all duration-500 ease-out"
                                style={{ width: `${((currentStageIndex + 1) / (stages.length + 1)) * 100}%` }}
                            ></div>
                        </div>
                    </div>

                    {/* Questions - Animated Keyed Wrapper */}
                    <div ref={stageRef} key={currentStageIndex} className="space-y-10">
                        {currentStage.items.map((q, index) => {
                            const type = normalizeQuestionType(q.type);
                            const choiceList = Array.isArray(q.choices)
                                ? q.choices
                                : (Array.isArray(q.options)
                                    ? q.options.map((opt, idx) => (typeof opt === 'object'
                                        ? { ...opt, id: opt.id || `choice_${idx + 1}`, label: opt.label || '' }
                                        : { id: `choice_${idx + 1}`, label: opt }))
                                    : []);
                            const rankingItems = Array.isArray(q?.ranking?.items) ? q.ranking.items : [];
                            const matchingPairs = Array.isArray(q?.matching?.pairs) ? q.matching.pairs : [];
                            const matrixStatements = Array.isArray(q?.matrix?.statements) ? q.matrix.statements : [];

                            return (
                            <div key={q.id || index} className="pb-8 border-b border-base-200 last:border-0">
                                <div className="flex flex-col gap-4">

                                    {/* Question Info */}
                                    <div className="w-full">
                                        {q.indicator && (
                                            <div className="inline-block bg-smc-teal/10 text-smc-teal rounded-full px-3 py-1 text-xs font-bold tracking-wide mb-3">
                                                {q.indicator}
                                            </div>
                                        )}
                                        <p className="font-bold text-xl md:text-2xl text-base-content leading-snug">
                                            {q.text || q.indicator}
                                        </p>
                                        {q.guidance && (
                                            <div className="mt-3 text-sm text-base-content/60 italic">
                                                {q.guidance}
                                            </div>
                                        )}
                                    </div>

                                    {/* Input Area */}
                                    <div className="w-full mt-2">
                                        {/* TEXT INPUT */}
                                        {type === 'short_text' && (
                                            <input
                                                type="text"
                                                className="w-full bg-base-200/50 focus:bg-base-200 text-base-content placeholder-base-content/40 border border-base-300 rounded-xl px-5 py-4 focus:outline-none focus:ring-2 focus:ring-smc-teal/20 focus:border-smc-teal transition-all backdrop-blur-sm"
                                                value={answers[q.id] || ''}
                                                onChange={(e) => handleAnswerChange(q.id, e.target.value)}
                                                placeholder={__('Type your answer...', 'smc-viable')}
                                            />
                                        )}

                                        {/* OPTIONS (Radio Style) - Premium Cards */}
                                        {(type === 'single_choice' && choiceList.length > 0) && (
                                            <div className="grid gap-3">
                                                {choiceList.map((opt, idx) => {
                                                    const label = opt?.label || '';
                                                    const value = String(opt?.id || label);
                                                    const isSelected = `${answers[q.id]}` === value || `${answers[q.id]}` === label;

                                                    return (
                                                        <label
                                                            key={idx}
                                                            className={`
                                                                relative flex items-center p-5 rounded-xl border cursor-pointer transition-all duration-300 group
                                                                ${isSelected
                                                                    ? 'bg-smc-teal/10 border-smc-teal shadow-lg shadow-smc-teal/5'
                                                                    : 'bg-base-200 border-base-300 hover:border-smc-teal/50 hover:bg-base-300'}
                                                            `}
                                                        >
                                                            <input
                                                                type="radio"
                                                                name={`question_${q.id}`}
                                                                value={value}
                                                                checked={isSelected}
                                                                onChange={() => handleAnswerChange(q.id, value)}
                                                                className="sr-only"
                                                            />
                                                            <div className={`
                                                                w-6 h-6 rounded-full border-2 mr-4 flex items-center justify-center transition-colors
                                                                ${isSelected ? 'border-smc-teal bg-smc-teal' : 'border-base-content/30 group-hover:border-smc-teal'}
                                                            `}>
                                                                {isSelected && <CheckCircle2 size={14} className="text-white" />}
                                                            </div>
                                                            <span className={`text-lg font-medium ${isSelected ? 'text-smc-teal' : 'text-base-content'}`}>
                                                                {label}
                                                            </span>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        )}

                                        {/* MULTI SELECT */}
                                        {(type === 'multi_select' && choiceList.length > 0) && (
                                            <div className="grid gap-3">
                                                {choiceList.map((opt, idx) => {
                                                    const label = opt?.label || '';
                                                    const value = String(opt?.id || label);
                                                    const selected = Array.isArray(answers[q.id]) ? answers[q.id].map(String) : [];
                                                    const isSelected = selected.includes(value) || selected.includes(label);

                                                    return (
                                                        <label
                                                            key={idx}
                                                            className={`
                                                                relative flex items-center p-5 rounded-xl border cursor-pointer transition-all duration-300 group
                                                                ${isSelected
                                                                    ? 'bg-smc-teal/10 border-smc-teal shadow-lg shadow-smc-teal/5'
                                                                    : 'bg-base-200 border-base-300 hover:border-smc-teal/50 hover:bg-base-300'}
                                                            `}
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                value={value}
                                                                checked={isSelected}
                                                                onChange={(e) => {
                                                                    const prev = Array.isArray(answers[q.id]) ? answers[q.id].map(String) : [];
                                                                    const next = e.target.checked
                                                                        ? [...new Set([...prev, value])]
                                                                        : prev.filter((id) => id !== value && id !== label);
                                                                    handleAnswerChange(q.id, next);
                                                                }}
                                                                className="sr-only"
                                                            />
                                                            <div className={`
                                                                w-6 h-6 rounded border-2 mr-4 flex items-center justify-center transition-colors
                                                                ${isSelected ? 'border-smc-teal bg-smc-teal' : 'border-base-content/30 group-hover:border-smc-teal'}
                                                            `}>
                                                                {isSelected && <CheckCircle2 size={14} className="text-white" />}
                                                            </div>
                                                            <span className={`text-lg font-medium ${isSelected ? 'text-smc-teal' : 'text-base-content'}`}>
                                                                {label}
                                                            </span>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        )}

                                        {/* NUMERIC INPUT */}
                                        {type === 'numeric' && (
                                            <input
                                                type="number"
                                                className="w-full bg-base-200/50 focus:bg-base-200 text-base-content placeholder-base-content/40 border border-base-300 rounded-xl px-5 py-4 focus:outline-none focus:ring-2 focus:ring-smc-teal/20 focus:border-smc-teal transition-all backdrop-blur-sm"
                                                value={answers[q.id] || ''}
                                                onChange={(e) => handleAnswerChange(q.id, e.target.value)}
                                                placeholder={__('Enter value...', 'smc-viable')}
                                            />
                                        )}

                                        {/* RANKING */}
                                        {type === 'ranking' && rankingItems.length > 0 && (
                                            <div className="space-y-3">
                                                {rankingItems.map((item, itemIndex) => (
                                                    <div key={item.id || itemIndex} className="grid grid-cols-12 gap-3 items-center">
                                                        <div className="col-span-8 text-base-content">{item.label}</div>
                                                        <select
                                                            className="select select-bordered col-span-4"
                                                            value={answers?.[q.id]?.[item.id] || ''}
                                                            onChange={(e) => handleObjectAnswerChange(q.id, item.id, Number(e.target.value || 0))}
                                                        >
                                                            <option value="">Rank</option>
                                                            {rankingItems.map((_, rankIndex) => (
                                                                <option key={rankIndex} value={rankIndex + 1}>{rankIndex + 1}</option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        {/* MATCHING */}
                                        {type === 'matching' && matchingPairs.length > 0 && (
                                            <div className="space-y-3">
                                                {matchingPairs.map((pair, pairIndex) => (
                                                    <div key={pair.id || pairIndex} className="grid grid-cols-12 gap-3 items-center">
                                                        <div className="col-span-6 text-base-content">{pair.left}</div>
                                                        <select
                                                            className="select select-bordered col-span-6"
                                                            value={answers?.[q.id]?.[pair.id] || ''}
                                                            onChange={(e) => handleObjectAnswerChange(q.id, pair.id, e.target.value)}
                                                        >
                                                            <option value="">Select match</option>
                                                            {matchingPairs.map((optionPair, optionIndex) => (
                                                                <option key={optionIndex} value={optionPair.right}>{optionPair.right}</option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        {/* MATRIX TRUE/FALSE */}
                                        {type === 'matrix_true_false' && matrixStatements.length > 0 && (
                                            <div className="space-y-4">
                                                {matrixStatements.map((statement, stmtIndex) => (
                                                    <div key={statement.id || stmtIndex} className="grid grid-cols-12 gap-2 items-center">
                                                        <div className="col-span-7 text-base-content">{statement.text}</div>
                                                        <label className="col-span-2 text-sm flex items-center gap-2">
                                                            <input
                                                                type="radio"
                                                                name={`matrix_${q.id}_${statement.id}`}
                                                                checked={answers?.[q.id]?.[statement.id] === true || answers?.[q.id]?.[statement.id] === 'true'}
                                                                onChange={() => handleObjectAnswerChange(q.id, statement.id, true)}
                                                            />
                                                            True
                                                        </label>
                                                        <label className="col-span-3 text-sm flex items-center gap-2">
                                                            <input
                                                                type="radio"
                                                                name={`matrix_${q.id}_${statement.id}`}
                                                                checked={answers?.[q.id]?.[statement.id] === false || answers?.[q.id]?.[statement.id] === 'false'}
                                                                onChange={() => handleObjectAnswerChange(q.id, statement.id, false)}
                                                            />
                                                            False
                                                        </label>
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        {/* DATE INPUT */}
                                        {type === 'date_month' && (
                                            <input
                                                type="month"
                                                className="w-full bg-base-200/50 text-base-content border-base-300 rounded-xl px-5 py-4 focus:outline-none focus:ring-2 focus:ring-smc-teal/20 focus:border-smc-teal transition-all"
                                                value={answers[q.id] || ''}
                                                onChange={(e) => handleAnswerChange(q.id, e.target.value)}
                                            />
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                        })}
                    </div>

                    {/* Navigation */}
                    <div className="flex justify-between mt-12 pt-8 border-t border-base-200">
                        <button
                            className={`flex items-center gap-2 px-6 py-3 rounded-full font-bold text-base-content/50 hover:text-base-content transition-colors ${currentStageIndex === 0 ? 'opacity-0 pointer-events-none' : ''}`}
                            onClick={prevStage}
                            disabled={currentStageIndex === 0}
                        >
                            <ArrowLeft size={18} />
                            {__('Back', 'smc-viable')}
                        </button>

                        <button
                            className="flex items-center gap-3 bg-red-700 hover:bg-red-800 text-white px-8 py-4 rounded-full font-bold uppercase tracking-wider text-sm shadow-lg shadow-red-900/20 hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
                            onClick={nextStage}
                        >
                            {currentStageIndex === stages.length - 1 ? __('Finish & Review', 'smc-viable') : __('Next Stage', 'smc-viable')}
                            <ArrowRight size={18} />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
