import { useState, useEffect, useRef } from '@wordpress/element';
import { ArrowLeft, Plus, Trash2, Save, CheckCircle2, AlertCircle } from 'lucide-react';

export default function QuizEnrollmentRules({ onBack }) {
    const [quizzes, setQuizzes] = useState([]);
    const [selectedQuizId, setSelectedQuizId] = useState(null);
    const [rules, setRules] = useState([]);
    const [courses, setCourses] = useState([]); // For selection
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [toast, setToast] = useState(null);
    const toastTimerRef = useRef(null);

    const showToast = (status, text) => {
        setToast({ status, text });
        if (toastTimerRef.current) {
            clearTimeout(toastTimerRef.current);
        }
        toastTimerRef.current = setTimeout(() => setToast(null), 2800);
    };

    useEffect(() => {
        const calculateData = async () => {
            try {
                // Fetch Quizzes
                const quizRes = await fetch(`${wpApiSettings.root}smc/v1/quizzes`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                const quizData = await quizRes.json();
                setQuizzes(quizData);

                // Fetch Courses (Lightweight list)
                const courseRes = await fetch(`${wpApiSettings.root}smc/v1/instructor/courses-list`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                const courseData = await courseRes.json();
                setCourses(courseData);
            } catch (err) {
                console.error("Failed to load data", err);
            } finally {
                setLoading(false);
            }
        };
        calculateData();
        return () => {
            if (toastTimerRef.current) {
                clearTimeout(toastTimerRef.current);
            }
        };
    }, []);

    useEffect(() => {
        if (!selectedQuizId) return;

        const fetchRules = async () => {
            try {
                const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/quiz-enrollment-rules/${selectedQuizId}`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                const data = await response.json();
                // Ensure array
                setRules(Array.isArray(data) ? data : []);
            } catch (err) {
                console.error("Failed to fetch rules", err);
            }
        };
        fetchRules();
    }, [selectedQuizId]);

    const handleSave = async () => {
        if (!selectedQuizId) return;
        setSaving(true);
        try {
            await fetch(`${wpApiSettings.root}smc/v1/instructor/quiz-enrollment-rules/${selectedQuizId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({ rules })
            });
            showToast('success', 'Rules saved.');
        } catch (err) {
            console.error("Failed to save", err);
            showToast('error', 'Error saving rules.');
        } finally {
            setSaving(false);
        }
    };

    const addRule = () => {
        setRules([...rules, {
            condition: { operator: 'gte', value: 0 },
            courses: [],
            recommended_sections: []
        }]);
    };

    const updateRule = (index, field, value) => {
        const newRules = [...rules];
        newRules[index][field] = value;
        setRules(newRules);
    };

    const updateCondition = (index, field, value) => {
        const newRules = [...rules];
        newRules[index].condition = { ...newRules[index].condition, [field]: value };
        setRules(newRules);
    };

    if (loading) return <div>Loading...</div>;

    return (
        <div className="smc-quiz-rules-manager">
            <header className="smc-editor-header mb-6">
                <button onClick={onBack} className="smc-btn-secondary">
                    <ArrowLeft size={16} className="mr-2" /> Back to Courses
                </button>
                <h2 className="text-xl font-bold text-base-content">Quiz Enrollment Rules</h2>
            </header>

            <div className="flex gap-6">
                {/* Sidebar */}
                <div className="w-1/3 bg-base-200 p-6 rounded-2xl border border-base-content/5">
                    <h3 className="mb-6 text-base-content/40 text-xs font-bold uppercase tracking-widest">Select Quiz</h3>
                    <ul className="space-y-2">
                        {quizzes.map(quiz => (
                            <li
                                key={quiz.id}
                                className={`p-4 rounded-xl cursor-pointer transition-all duration-300 font-medium ${selectedQuizId === quiz.id ? 'bg-teal-500 text-white shadow-lg shadow-teal-500/20' : 'bg-base-300/50 hover:bg-base-300 text-base-content/70 hover:text-base-content'}`}
                                onClick={() => setSelectedQuizId(quiz.id)}
                            >
                                {quiz.title.rendered}
                            </li>
                        ))}
                    </ul>
                </div>

                {/* Editor Area */}
                <div className="w-2/3 bg-base-200 p-8 rounded-2xl border border-base-content/5">
                    {!selectedQuizId ? (
                        <div className="text-center text-base-content/30 py-10">Select a quiz to configure rules.</div>
                    ) : (
                        <>
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-lg font-bold text-base-content">Rules for Selected Quiz</h3>
                                <button className="smc-btn-primary" onClick={handleSave} disabled={saving}>
                                    <Save size={16} className="mr-2" />
                                    {saving ? 'Saving...' : 'Save Rules'}
                                </button>
                            </div>

                            <div className="space-y-6">
                                {rules.map((rule, idx) => (
                                    <div key={idx} className="bg-base-300/50 p-6 rounded-2xl border border-base-content/5 relative group">
                                        <button
                                            className="absolute top-2 right-2 text-red-400 hover:text-red-300"
                                            onClick={() => {
                                                const newRules = [...rules];
                                                newRules.splice(idx, 1);
                                                setRules(newRules);
                                            }}
                                        >
                                            <Trash2 size={16} />
                                        </button>

                                        <div className="mb-4">
                                            <label className="block text-xs font-bold uppercase text-base-content/40 mb-3 tracking-wider">Condition (Total Score)</label>
                                            <div className="flex gap-2">
                                                <select
                                                    className="bg-base-100 border-base-content/10 rounded-lg text-base-content focus:ring-teal-500/20"
                                                    value={rule.condition.operator}
                                                    onChange={(e) => updateCondition(idx, 'operator', e.target.value)}
                                                >
                                                    <option value="gte">{"Greater or Equal (>=)"}</option>
                                                    <option value="gt">{"Greater Than (>)"}</option>
                                                    <option value="lte">{"Less or Equal (<=)"}</option>
                                                    <option value="lt">{"Less Than (<)"}</option>
                                                    <option value="between">Between</option>
                                                </select>

                                                <input
                                                    type="number"
                                                    className="bg-base-100 border-base-content/10 rounded-lg text-base-content w-24 focus:ring-teal-500/20"
                                                    value={rule.condition.value}
                                                    onChange={(e) => updateCondition(idx, 'value', parseInt(e.target.value))}
                                                    placeholder="Value"
                                                />

                                                {rule.condition.operator === 'between' && (
                                                    <>
                                                        <span className="self-center text-base-content/60">and</span>
                                                        <input
                                                            type="number"
                                                            className="bg-base-100 border-base-content/10 rounded-lg text-base-content w-24 focus:ring-teal-500/20"
                                                            value={rule.condition.max}
                                                            onChange={(e) => updateCondition(idx, 'max', parseInt(e.target.value))}
                                                            placeholder="Max"
                                                        />
                                                    </>
                                                )}
                                            </div>
                                        </div>

                                        <div className="mb-4">
                                            <label className="block text-xs font-bold uppercase text-base-content/40 mb-3 tracking-wider">Unlock Courses</label>
                                            <div className="flex flex-wrap gap-2">
                                                {courses.map(course => (
                                                    <label key={course.id} className="inline-flex items-center bg-base-100/50 px-4 py-2 rounded-xl cursor-pointer hover:bg-teal-500/10 hover:text-teal-500 transition-all border border-base-content/5">
                                                        <input
                                                            type="checkbox"
                                                            className="mr-3 checkbox checkbox-xs checkbox-primary"
                                                            checked={(rule.courses || []).includes(course.id)}
                                                            onChange={(e) => {
                                                                const current = rule.courses || [];
                                                                let updated;
                                                                if (e.target.checked) {
                                                                    updated = [...current, course.id];
                                                                } else {
                                                                    updated = current.filter(id => id !== course.id);
                                                                }
                                                                updateRule(idx, 'courses', updated);
                                                            }}
                                                        />
                                                        {course.title}
                                                    </label>
                                                ))}
                                            </div>
                                        </div>

                                        {/* Recommended Sections - Simple JSON/Text input for now */}
                                        {/* Currently API expects generic recommended_sections array. */}
                                        {/* UI wise, this is hard without knowing course structure. Let's provide a raw input or skip for MVP. */}
                                        {/* Let's skip detailed section recommendation UI for this iteration and focus on course unlock. */}
                                    </div>
                                ))}

                                <button
                                    className="w-full py-4 border-2 border-dashed border-base-content/10 text-base-content/40 rounded-2xl hover:border-teal-500/30 hover:text-teal-500 transition-all font-bold"
                                    onClick={addRule}
                                >
                                    <Plus size={18} className="inline mr-2" /> Add New Rule
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </div>

            {toast && (
                <div className="smc-toaster smc-instructor-toaster">
                    <div className={`smc-toast ${toast.status}`}>
                        <div className="toast-icon">
                            {toast.status === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
                        </div>
                        <div className="toast-content">
                            <h4>{toast.status === 'success' ? 'Success' : 'Error'}</h4>
                            <p>{toast.text}</p>
                        </div>
                        <div className="toast-timer" style={{ animationDuration: '2800ms' }}></div>
                    </div>
                </div>
            )}
        </div>
    );
}
