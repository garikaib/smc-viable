import { __ } from '@wordpress/i18n';
import { Button, TextControl, Spinner, Notice, TabPanel, TextareaControl, RadioControl, ToggleControl } from '@wordpress/components';
import { useState, useEffect, useMemo, useCallback, useRef } from '@wordpress/element';
import { ArrowLeft, Plus, Save, LoaderCircle, CheckCircle2, Clock3, LayoutPanelTop, SlidersHorizontal, ChartColumnIncreasing, Trash2, PlusCircle } from 'lucide-react';
import { fetchQuiz, saveQuiz } from '../utils/api';
import QuestionEditor from './QuestionEditor';
import DashboardRuleEditor from './DashboardRuleEditor';

const DEFAULT_SETTINGS = {
    delivery_mode: 'download',
    guest_pdf_access: 'account_required',
    logged_in_email_link: true,
    guest_email_capture: true
};
const PLAN_OPTIONS = Array.isArray(window.smcQuizSettings?.planTiers) && window.smcQuizSettings.planTiers.length
    ? window.smcQuizSettings.planTiers
    : [
        { label: 'Free Plan', value: 'free' },
        { label: 'Basic', value: 'basic' },
        { label: 'Standard', value: 'standard' },
    ];

const DRAFT_VERSION = 1;
const DRAFT_STORAGE_PREFIX = 'smc_quiz_editor_draft_v1';
const MAX_DRAFT_AGE_MS = 1000 * 60 * 60 * 24 * 7;
const getDraftStorageKey = (id) => `${DRAFT_STORAGE_PREFIX}:${id ? `quiz-${id}` : 'new'}`;

const parseDraft = (raw) => {
    if (!raw) return null;
    try {
        const parsed = JSON.parse(raw);
        if (!parsed || parsed.version !== DRAFT_VERSION || typeof parsed !== 'object') return null;
        const updatedAtMs = parsed.updatedAt ? new Date(parsed.updatedAt).getTime() : 0;
        if (!updatedAtMs || Number.isNaN(updatedAtMs)) return null;
        if (Date.now() - updatedAtMs > MAX_DRAFT_AGE_MS) return null;
        return parsed;
    } catch (_) {
        return null;
    }
};

export default function QuizEditor({ quizId, onBack, onPersistedQuiz }) {
    const [title, setTitle] = useState('');
    const [questions, setQuestions] = useState([]);
    const [activeQuestionIndex, setActiveQuestionIndex] = useState(0);
    const [activeTabName, setActiveTabName] = useState('questions');
    const [settings, setSettings] = useState(DEFAULT_SETTINGS);
    const [stages, setStages] = useState(['Market & Offering', 'Business Model', 'Execution']);
    const [planLevel, setPlanLevel] = useState('free');
    const [shopSettings, setShopSettings] = useState({
        enabled: false,
        access_mode: 'standalone',
        assigned_plan: 'free',
        price: 0,
        features: []
    });
    const [dashboardTitle, setDashboardTitle] = useState('Assessment Results');
    const [dashboardRules, setDashboardRules] = useState([]);
    const [showJsonEditor, setShowJsonEditor] = useState(false);
    const [isLoading, setIsLoading] = useState(!!quizId);
    const [isSaving, setIsSaving] = useState(false);
    const [lastSavedSnapshot, setLastSavedSnapshot] = useState(null);
    const [lastSavedAt, setLastSavedAt] = useState(null);
    const [notice, setNotice] = useState(null);
    const draftHydratedRef = useRef(false);

    const draftStorageKey = useMemo(() => getDraftStorageKey(quizId), [quizId]);

    const applyHydratedState = useCallback((state) => {
        if (!state || typeof state !== 'object') return;
        if (typeof state.title === 'string') setTitle(state.title);
        if (Array.isArray(state.questions)) setQuestions(state.questions);
        if (Number.isInteger(state.activeQuestionIndex)) setActiveQuestionIndex(state.activeQuestionIndex);
        if (typeof state.activeTabName === 'string') setActiveTabName(state.activeTabName);
        if (state.settings && typeof state.settings === 'object') setSettings({ ...DEFAULT_SETTINGS, ...state.settings });
        if (Array.isArray(state.stages) && state.stages.length > 0) setStages(state.stages);
        if (typeof state.planLevel === 'string' && state.planLevel) setPlanLevel(state.planLevel);
        if (state.shopSettings && typeof state.shopSettings === 'object') {
            setShopSettings({
                enabled: !!state.shopSettings.enabled,
                access_mode: state.shopSettings.access_mode || 'standalone',
                assigned_plan: state.shopSettings.assigned_plan || 'free',
                price: state.shopSettings.price || 0,
                features: Array.isArray(state.shopSettings.features) ? state.shopSettings.features : []
            });
        }
        if (typeof state.dashboardTitle === 'string') setDashboardTitle(state.dashboardTitle || 'Assessment Results');
        if (Array.isArray(state.dashboardRules)) setDashboardRules(state.dashboardRules);
        if (typeof state.showJsonEditor === 'boolean') setShowJsonEditor(state.showJsonEditor);
        if (typeof state.lastSavedSnapshot === 'string' || state.lastSavedSnapshot === null) setLastSavedSnapshot(state.lastSavedSnapshot);
        if (state.lastSavedAt) {
            const parsed = new Date(state.lastSavedAt);
            setLastSavedAt(Number.isNaN(parsed.getTime()) ? null : parsed);
        }
    }, []);

    useEffect(() => {
        draftHydratedRef.current = false;
        setNotice(null);
        setIsLoading(!!quizId);

        const draft = parseDraft(window.localStorage.getItem(draftStorageKey));
        const hasDraft = !!draft?.state;

        if (hasDraft) {
            applyHydratedState(draft.state);
            draftHydratedRef.current = true;
        } else if (!quizId) {
            setTitle('');
            setQuestions([]);
            setActiveQuestionIndex(-1);
            setActiveTabName('questions');
            setSettings(DEFAULT_SETTINGS);
            setStages(['Market & Offering', 'Business Model', 'Execution']);
            setPlanLevel('free');
            setShopSettings({
                enabled: false,
                access_mode: 'standalone',
                assigned_plan: 'free',
                price: 0,
                features: []
            });
            setDashboardTitle('Assessment Results');
            setDashboardRules([]);
            setShowJsonEditor(false);
            setLastSavedSnapshot(null);
            setLastSavedAt(null);
        }

        if (!quizId) {
            setIsLoading(false);
            draftHydratedRef.current = true;
            return;
        }

        fetchQuiz(quizId)
            .then((data) => {
                if (hasDraft) return;
                const meta = data.meta || {};
                const metaQuestions = meta._smc_quiz_questions || [];
                const parsedQuestions = Array.isArray(metaQuestions) ? metaQuestions : [];

                setTitle(data.title?.rendered || '');
                setQuestions(parsedQuestions);
                setActiveQuestionIndex(parsedQuestions.length ? 0 : -1);
                setActiveTabName('questions');

                const incomingSettings = typeof meta._smc_quiz_settings === 'object' && meta._smc_quiz_settings
                    ? meta._smc_quiz_settings
                    : {};
                setSettings({ ...DEFAULT_SETTINGS, ...incomingSettings });

                setStages(meta._smc_quiz_stages && Array.isArray(meta._smc_quiz_stages) && meta._smc_quiz_stages.length > 0
                    ? meta._smc_quiz_stages
                    : ['Market & Offering', 'Business Model', 'Execution']);
                setPlanLevel(meta._smc_quiz_plan_level || 'free');

                const incomingShop = meta._smc_quiz_shop || {};
                setShopSettings({
                    enabled: !!incomingShop.enabled,
                    access_mode: incomingShop.access_mode || 'standalone',
                    assigned_plan: incomingShop.assigned_plan || 'free',
                    price: incomingShop.price || 0,
                    features: Array.isArray(incomingShop.features) ? incomingShop.features : []
                });

                if (meta._smc_quiz_dashboard_config) {
                    try {
                        const conf = typeof meta._smc_quiz_dashboard_config === 'string'
                            ? JSON.parse(meta._smc_quiz_dashboard_config)
                            : meta._smc_quiz_dashboard_config;
                        if (conf && conf.dashboard_config) {
                            setDashboardTitle(conf.dashboard_config.title || 'Assessment Results');
                            setDashboardRules(conf.dashboard_config.rules || []);
                        } else {
                            setDashboardTitle('Assessment Results');
                            setDashboardRules([]);
                        }
                    } catch (e) {
                        setNotice({ status: 'error', text: __('Failed to parse dashboard config.', 'smc-viable') });
                    }
                } else {
                    setDashboardTitle('Assessment Results');
                    setDashboardRules([]);
                }
                setShowJsonEditor(false);
            })
            .catch((err) => setNotice({ status: 'error', text: err.message }))
            .finally(() => {
                setIsLoading(false);
                draftHydratedRef.current = true;
            });
    }, [applyHydratedState, draftStorageKey, quizId]);

    const savePayload = useMemo(() => {
        const fullDashboardConfig = {
            version: '1.0.0',
            dashboard_config: { title: dashboardTitle, rules: dashboardRules }
        };
        return {
            id: quizId,
            title: title || __('New Quiz', 'smc-viable'),
            status: 'publish',
            questions,
            settings,
            dashboard_config: fullDashboardConfig,
            stages,
            plan_level: planLevel,
            shop: { ...shopSettings, price: Number(shopSettings.price || 0) }
        };
    }, [quizId, title, questions, settings, dashboardTitle, dashboardRules, stages, planLevel, shopSettings]);

    const currentSnapshot = useMemo(() => JSON.stringify(savePayload), [savePayload]);
    const isDirty = lastSavedSnapshot !== null && currentSnapshot !== lastSavedSnapshot;

    useEffect(() => {
        if (lastSavedSnapshot === null && !isLoading) {
            setLastSavedSnapshot(currentSnapshot);
        }
    }, [currentSnapshot, isLoading, lastSavedSnapshot]);

    useEffect(() => {
        if (isLoading || !draftHydratedRef.current) return;
        const timer = setTimeout(() => {
            const draftState = {
                title,
                questions,
                activeQuestionIndex,
                activeTabName,
                settings,
                stages,
                planLevel,
                shopSettings,
                dashboardTitle,
                dashboardRules,
                showJsonEditor,
                lastSavedSnapshot,
                lastSavedAt: lastSavedAt ? lastSavedAt.toISOString() : null
            };
            try {
                window.localStorage.setItem(
                    draftStorageKey,
                    JSON.stringify({ version: DRAFT_VERSION, updatedAt: new Date().toISOString(), state: draftState })
                );
            } catch (_) {
                // ignore storage quota / privacy mode failures
            }
        }, 250);
        return () => clearTimeout(timer);
    }, [
        isLoading,
        draftStorageKey,
        title,
        questions,
        activeQuestionIndex,
        activeTabName,
        settings,
        stages,
        planLevel,
        shopSettings,
        dashboardTitle,
        dashboardRules,
        showJsonEditor,
        lastSavedSnapshot,
        lastSavedAt
    ]);

    const handleSave = useCallback(({ silent = false } = {}) => {
        if (isSaving) return;
        setIsSaving(true);
        if (!silent) {
            setNotice(null);
        }

        saveQuiz(savePayload)
            .then((saved) => {
                setLastSavedSnapshot(currentSnapshot);
                setLastSavedAt(new Date());
                const savedId = Number(saved?.id || 0);
                if (!quizId && savedId > 0 && typeof onPersistedQuiz === 'function') {
                    const savedTitle = saved?.title?.rendered || title;
                    onPersistedQuiz(savedId, { title: savedTitle });
                }
                if (!silent) {
                    setNotice({ status: 'success', text: __('Assessment saved successfully.', 'smc-viable') });
                }
            })
            .catch((err) => {
                if (silent) {
                    setNotice({ status: 'error', text: __('Autosave failed. Please save manually.', 'smc-viable') });
                } else {
                    setNotice({ status: 'error', text: err.message });
                }
            })
            .finally(() => setIsSaving(false));
    }, [isSaving, savePayload, currentSnapshot, quizId, onPersistedQuiz, title]);

    useEffect(() => {
        if (!quizId || isLoading || isSaving || !isDirty) return;
        const timer = setTimeout(() => {
            handleSave({ silent: true });
        }, 10000);
        return () => clearTimeout(timer);
    }, [quizId, isLoading, isSaving, isDirty, handleSave, currentSnapshot]);

    const addQuestion = () => {
        const newQ = {
            id: `q_${Date.now()}`,
            version: 2,
            type: 'single_choice',
            stage: stages[0] || 'Other',
            indicator: '',
            text: '',
            choices: [
                { id: `choice_${Date.now()}_1`, label: 'Option 1', points: 0 },
                { id: `choice_${Date.now()}_2`, label: 'Option 2', points: 0 },
            ],
            grading: { mode: 'auto', max_points: 0, min_points: 0 }
        };
        const next = [...questions, newQ];
        setQuestions(next);
        setActiveQuestionIndex(next.length - 1);
    };

    const updateQuestion = (index, newQ) => {
        const next = [...questions];
        next[index] = newQ;
        setQuestions(next);
    };

    const removeQuestion = (index) => {
        const next = questions.filter((_, i) => i !== index);
        setQuestions(next);
        if (!next.length) setActiveQuestionIndex(-1);
        else if (index <= activeQuestionIndex) setActiveQuestionIndex(Math.max(0, activeQuestionIndex - 1));
    };

    const cloneQuestion = (index) => {
        if (index < 0 || index >= questions.length) return;
        const source = questions[index];
        const copy = {
            ...source,
            id: Date.now(),
            text: source.text ? `${source.text} (Copy)` : 'Copied Question',
            options: Array.isArray(source.options)
                ? source.options.map((opt) => (typeof opt === 'object' ? { ...opt } : opt))
                : []
        };
        const next = [...questions];
        next.splice(index + 1, 0, copy);
        setQuestions(next);
        setActiveQuestionIndex(index + 1);
    };

    const addStage = () => setStages([...stages, 'New Stage']);
    const updateStage = (idx, val) => {
        const next = [...stages];
        next[idx] = val;
        setStages(next);
    };
    const removeStage = (idx) => setStages(stages.filter((_, i) => i !== idx));

    const addRule = () => {
        setDashboardRules([
            ...dashboardRules,
            {
                id: `rule_${Date.now()}`,
                condition_text: 'New Condition',
                logic: { operator: 'gt', value: 50, min: 0, max: 0 },
                message: 'Feedback message here...',
                style: { color: 'green', icon: 'check' }
            }
        ]);
    };
    const updateRule = (index, newRule) => {
        const next = [...dashboardRules];
        next[index] = newRule;
        setDashboardRules(next);
    };
    const removeRule = (index) => setDashboardRules(dashboardRules.filter((_, i) => i !== index));

    useEffect(() => {
        if (!questions.length && activeQuestionIndex !== -1) {
            setActiveQuestionIndex(-1);
            return;
        }
        if (questions.length && (activeQuestionIndex < 0 || activeQuestionIndex >= questions.length)) {
            setActiveQuestionIndex(0);
        }
    }, [questions, activeQuestionIndex]);

    if (isLoading) return <Spinner />;

    const activeQuestion = questions[activeQuestionIndex];
    const saveStatusText = isSaving
        ? __('Saving...', 'smc-viable')
        : isDirty
            ? __('Unsaved changes', 'smc-viable')
            : lastSavedAt
                ? __('All changes saved', 'smc-viable')
                : __('Ready', 'smc-viable');

    return (
        <div className="smc-quiz-editor smc-panel">
            <div className="smc-editor-header">
                <button type="button" className="smc-quiz-header-action smc-quiz-header-action-back" onClick={onBack}>
                    <ArrowLeft size={16} />
                    <span>{__('Back to Library', 'smc-viable')}</span>
                </button>
                <div className="smc-editor-header-actions">
                    <button type="button" className="smc-quiz-header-action" onClick={addQuestion}>
                        <Plus size={16} />
                        <span>{__('Add Question', 'smc-viable')}</span>
                    </button>
                    <button type="button" className="smc-quiz-header-action smc-quiz-header-action-save" onClick={() => handleSave()}>
                        {isSaving ? <LoaderCircle size={16} className="smc-spin" /> : <Save size={16} />}
                        <span>{__('Save Now', 'smc-viable')}</span>
                    </button>
                    <span className={`smc-quiz-save-state ${isDirty ? 'is-dirty' : 'is-clean'}`}>
                        {isSaving ? <LoaderCircle size={14} className="smc-spin" /> : isDirty ? <Clock3 size={14} /> : <CheckCircle2 size={14} />}
                        <span>{saveStatusText}</span>
                    </span>
                </div>
            </div>

            <div className="smc-editor-title-row">
                <h2>{quizId ? __('Edit Assessment', 'smc-viable') : __('Create Assessment', 'smc-viable')}</h2>
                <p>{__('Use the visual editor to shape stages, scoring and report behavior.', 'smc-viable')}</p>
            </div>

            <TextControl
                label={__('Assessment Title', 'smc-viable')}
                value={title}
                onChange={setTitle}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
            />

            <TabPanel
                key={`tabs-${quizId || 'new'}-${activeTabName}`}
                className="my-tab-panel smc-editor-tabs"
                activeClass="is-active"
                initialTabName={activeTabName}
                onSelect={(tab) => setActiveTabName(typeof tab === 'string' ? tab : (tab?.name || 'questions'))}
                tabs={[
                    {
                        name: 'questions',
                        title: (
                            <span className="smc-tab-label">
                                <LayoutPanelTop size={15} className="smc-tab-icon" />
                                <span>{__('Visual Builder', 'smc-viable')}</span>
                            </span>
                        )
                    },
                    {
                        name: 'config',
                        title: (
                            <span className="smc-tab-label">
                                <SlidersHorizontal size={15} className="smc-tab-icon" />
                                <span>{__('Configuration', 'smc-viable')}</span>
                            </span>
                        )
                    },
                    {
                        name: 'dashboard',
                        title: (
                            <span className="smc-tab-label">
                                <ChartColumnIncreasing size={15} className="smc-tab-icon" />
                                <span>{__('Scoring Logic', 'smc-viable')}</span>
                            </span>
                        )
                    },
                ]}
            >
                {(tab) => {
                    if (tab.name === 'questions') {
                        return (
                            <div className="smc-visual-editor">
                                <aside className="smc-question-nav">
                                    <div className="smc-question-nav-head">
                                        <h3>{__('Questions', 'smc-viable')}</h3>
                                        <span>{questions.length}</span>
                                    </div>
                                    <div className="smc-question-list">
                                        {questions.map((q, index) => (
                                            <button
                                                type="button"
                                                key={q.id || index}
                                                className={`smc-question-pill ${index === activeQuestionIndex ? 'active' : ''}`}
                                                onClick={() => setActiveQuestionIndex(index)}
                                            >
                                                <strong>Q{index + 1}</strong>
                                                <span>{q.text || __('Untitled question', 'smc-viable')}</span>
                                            </button>
                                        ))}
                                    </div>
                                    <button type="button" className="smc-quiz-nav-add-inline" onClick={addQuestion}>
                                        <Plus size={15} />
                                        <span>{__('Add Question', 'smc-viable')}</span>
                                    </button>
                                </aside>

                                <section className="smc-question-canvas">
                                    {activeQuestion ? (
                                        <QuestionEditor
                                            question={activeQuestion}
                                            stages={stages}
                                            onChange={(newQ) => updateQuestion(activeQuestionIndex, newQ)}
                                            onClone={() => cloneQuestion(activeQuestionIndex)}
                                            onRemove={() => removeQuestion(activeQuestionIndex)}
                                        />
                                    ) : (
                                        <div className="smc-empty-state">
                                            <h3>{__('No questions yet', 'smc-viable')}</h3>
                                            <p>{__('Start by adding your first assessment question.', 'smc-viable')}</p>
                                        </div>
                                    )}
                                </section>
                            </div>
                        );
                    }

                    if (tab.name === 'config') {
                        return (
                            <div className="smc-editor-section smc-editor-section-config">
                                <h3>{__('Report Delivery', 'smc-viable')}</h3>
                                <RadioControl
                                    label={__('Guest PDF Access', 'smc-viable')}
                                    selected={settings.guest_pdf_access}
                                    options={[
                                        { label: __('Allow instant PDF download for guests', 'smc-viable'), value: 'public' },
                                        { label: __('Require login/registration before PDF download', 'smc-viable'), value: 'account_required' },
                                    ]}
                                    onChange={(val) => setSettings({ ...settings, guest_pdf_access: val })}
                                />
                                <ToggleControl
                                    label={__('Allow logged-in users to request an email download link', 'smc-viable')}
                                    checked={!!settings.logged_in_email_link}
                                    onChange={(val) => setSettings({ ...settings, logged_in_email_link: !!val })}
                                />
                                <ToggleControl
                                    label={__('Allow guests to email report link (captures email/phone leads)', 'smc-viable')}
                                    checked={!!settings.guest_email_capture}
                                    onChange={(val) => setSettings({ ...settings, guest_email_capture: !!val })}
                                />

                                <h3>{__('Access & Plan', 'smc-viable')}</h3>
                                <RadioControl
                                    label={__('Plan Level', 'smc-viable')}
                                    selected={planLevel}
                                    options={PLAN_OPTIONS}
                                    onChange={setPlanLevel}
                                />

                                <h3>{__('Shop Placement', 'smc-viable')}</h3>
                                <ToggleControl
                                    label={__('List this assessment in shop', 'smc-viable')}
                                    checked={!!shopSettings.enabled}
                                    onChange={(val) => setShopSettings({ ...shopSettings, enabled: !!val })}
                                />
                                {shopSettings.enabled && (
                                    <div className="smc-shop-config-grid">
                                        <RadioControl
                                            label={__('Access Model', 'smc-viable')}
                                            selected={shopSettings.access_mode}
                                            options={[
                                                { label: 'Standalone purchase', value: 'standalone' },
                                                { label: 'Assigned to plan only', value: 'plan' },
                                                { label: 'Both (purchase + plan)', value: 'both' },
                                            ]}
                                            onChange={(val) => setShopSettings({ ...shopSettings, access_mode: val })}
                                        />
                                        <RadioControl
                                            label={__('Assigned plan', 'smc-viable')}
                                            selected={shopSettings.assigned_plan}
                                            options={PLAN_OPTIONS}
                                            onChange={(val) => setShopSettings({ ...shopSettings, assigned_plan: val })}
                                        />
                                        <TextControl
                                            label={__('Standalone Price ($)', 'smc-viable')}
                                            type="number"
                                            value={shopSettings.price}
                                            onChange={(val) => setShopSettings({ ...shopSettings, price: val })}
                                        />
                                        <TextareaControl
                                            label={__('Shop Features (one per line)', 'smc-viable')}
                                            value={(shopSettings.features || []).join('\n')}
                                            onChange={(val) => setShopSettings({
                                                ...shopSettings,
                                                features: val.split('\n').map((line) => line.trim()).filter(Boolean)
                                            })}
                                        />
                                    </div>
                                )}

                                <h3>{__('Stages / Categories', 'smc-viable')}</h3>
                                <div className="smc-stage-list">
                                    {stages.map((stage, idx) => (
                                        <div key={idx} className="smc-stage-row">
                                            <TextControl
                                                value={stage}
                                                onChange={(val) => updateStage(idx, val)}
                                                __next40pxDefaultSize
                                                __nextHasNoMarginBottom
                                            />
                                            <button type="button" className="smc-inline-icon-btn smc-inline-icon-btn--danger" onClick={() => removeStage(idx)}>
                                                <Trash2 size={14} />
                                                <span>{__('Remove', 'smc-viable')}</span>
                                            </button>
                                        </div>
                                    ))}
                                </div>
                                <button type="button" className="smc-inline-icon-btn smc-inline-icon-btn--add" onClick={addStage}>
                                    <PlusCircle size={15} />
                                    <span>{__('Add Stage', 'smc-viable')}</span>
                                </button>
                            </div>
                        );
                    }

                    return (
                        <div className="smc-editor-section">
                            <div className="smc-dashboard-head">
                                <h3>{__('Scoring Rules', 'smc-viable')}</h3>
                                <ToggleControl
                                    label={__('Advanced (Raw JSON)', 'smc-viable')}
                                    checked={showJsonEditor}
                                    onChange={setShowJsonEditor}
                                />
                            </div>

                            <TextControl
                                label={__('Report Title', 'smc-viable')}
                                value={dashboardTitle}
                                onChange={setDashboardTitle}
                            />

                            {showJsonEditor ? (
                                <TextareaControl
                                    label={__('Raw JSON Config', 'smc-viable')}
                                    value={JSON.stringify({ version: '1.0.0', dashboard_config: { title: dashboardTitle, rules: dashboardRules } }, null, 2)}
                                    onChange={(val) => {
                                        try {
                                            const parsed = JSON.parse(val);
                                            if (parsed.dashboard_config) {
                                                setDashboardTitle(parsed.dashboard_config.title || dashboardTitle);
                                                setDashboardRules(parsed.dashboard_config.rules || []);
                                            }
                                        } catch (_) {
                                            // ignore invalid json while typing
                                        }
                                    }}
                                    rows={16}
                                />
                            ) : (
                                <div>
                                    {dashboardRules.map((rule, idx) => (
                                        <DashboardRuleEditor
                                            key={idx}
                                            rule={rule}
                                            onChange={(newRule) => updateRule(idx, newRule)}
                                            onRemove={() => removeRule(idx)}
                                        />
                                    ))}
                                    <Button isSecondary onClick={addRule}>{__('Add Scoring Rule', 'smc-viable')}</Button>
                                </div>
                            )}
                        </div>
                    );
                }}
            </TabPanel>

            {notice && (
                <Notice status={notice.status} onRemove={() => setNotice(null)} style={{ marginTop: '20px' }}>
                    {notice.text}
                </Notice>
            )}
        </div>
    );
}
