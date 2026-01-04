import { __ } from '@wordpress/i18n';
import { Button, TextControl, Spinner, Notice, TabPanel, TextareaControl, RadioControl, PanelBody, PanelRow, ToggleControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { fetchQuiz, saveQuiz } from '../utils/api';
import QuestionEditor from './QuestionEditor';
import DashboardRuleEditor from './DashboardRuleEditor';

export default function QuizEditor({ quizId, onBack }) {
    const [title, setTitle] = useState('');
    const [questions, setQuestions] = useState([]);

    // Config States
    const [settings, setSettings] = useState({ delivery_mode: 'download' });
    const [stages, setStages] = useState(['Market & Offering', 'Business Model', 'Execution']);

    // Dashboard States
    const [dashboardTitle, setDashboardTitle] = useState('Assessment Results');
    const [dashboardRules, setDashboardRules] = useState([]);
    const [showJsonEditor, setShowJsonEditor] = useState(false); // Toggle for advanced mode
    const [rawJson, setRawJson] = useState(''); // Only used if showJsonEditor is true

    const [isLoading, setIsLoading] = useState(!!quizId);
    const [isSaving, setIsSaving] = useState(false);
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        if (quizId) {
            fetchQuiz(quizId)
                .then((data) => {
                    setTitle(data.title.rendered);

                    const meta = data.meta || {};
                    const metaQuestions = meta._smc_quiz_questions || [];
                    setQuestions(Array.isArray(metaQuestions) ? metaQuestions : []);

                    if (meta._smc_quiz_settings) setSettings(meta._smc_quiz_settings);

                    if (meta._smc_quiz_stages && Array.isArray(meta._smc_quiz_stages) && meta._smc_quiz_stages.length > 0) {
                        setStages(meta._smc_quiz_stages);
                    }

                    // Parse Dashboard Config
                    if (meta._smc_quiz_dashboard_config) {
                        try {
                            const conf = typeof meta._smc_quiz_dashboard_config === 'string'
                                ? JSON.parse(meta._smc_quiz_dashboard_config)
                                : meta._smc_quiz_dashboard_config;

                            if (conf && conf.dashboard_config) {
                                setDashboardTitle(conf.dashboard_config.title || 'Assessment Results');
                                setDashboardRules(conf.dashboard_config.rules || []);
                            }
                        } catch (e) {
                            console.error("Failed to parse existing dashboard config", e);
                        }
                    }
                })
                .catch((err) => setNotice({ status: 'error', text: err.message }))
                .finally(() => setIsLoading(false));
        }
    }, [quizId]);

    const handleSave = () => {
        setIsSaving(true);
        setNotice(null);

        // Reconstruct Dashboard Config JSON
        const fullDashboardConfig = {
            version: "1.0.0",
            dashboard_config: {
                title: dashboardTitle,
                rules: dashboardRules
            }
        };

        const data = {
            id: quizId,
            title: title || __('New Quiz', 'smc-viable'),
            status: 'publish',
            questions: questions,
            settings: settings,
            dashboard_config: fullDashboardConfig,
            stages: stages
        };

        saveQuiz(data)
            .then(() => {
                setNotice({ status: 'success', text: __('Quiz saved successfully!', 'smc-viable') });
                if (!quizId) {
                    onBack();
                }
            })
            .catch((err) => setNotice({ status: 'error', text: err.message }))
            .finally(() => setIsSaving(false));
    };

    // --- Helpers ---
    const addQuestion = () => {
        const newQ = {
            id: Date.now(),
            type: 'scorable',
            stage: stages[0] || 'Other',
            indicator: '',
            text: '',
            options: []
        };
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

    // Stage Management
    const addStage = () => setStages([...stages, 'New Stage']);
    const updateStage = (idx, val) => {
        const newStages = [...stages];
        newStages[idx] = val;
        setStages(newStages);
    };
    const removeStage = (idx) => {
        const newStages = stages.filter((_, i) => i !== idx);
        setStages(newStages);
    };

    // Dashboard Rule Management
    const addRule = () => {
        const newRule = {
            id: `rule_${Date.now()}`,
            condition_text: 'New Condition',
            logic: { operator: 'gt', value: 50, min: 0, max: 0 },
            message: 'Feedback message here...',
            style: { color: 'green', icon: 'check' }
        };
        setDashboardRules([...dashboardRules, newRule]);
    };

    const updateRule = (index, newRule) => {
        const newRules = [...dashboardRules];
        newRules[index] = newRule;
        setDashboardRules(newRules);
    };

    const removeRule = (index) => {
        const newRules = dashboardRules.filter((_, i) => i !== index);
        setDashboardRules(newRules);
    };

    const handleImportJson = (jsonString) => {
        try {
            const parsed = JSON.parse(jsonString);
            if (parsed.dashboard_config) {
                setDashboardTitle(parsed.dashboard_config.title || dashboardTitle);
                setDashboardRules(parsed.dashboard_config.rules || []);
                setNotice({ status: 'success', text: 'Dashboard config imported!' });
            } else {
                setNotice({ status: 'error', text: 'Invalid JSON format: missing dashboard_config' });
            }
        } catch (e) {
            setNotice({ status: 'error', text: 'Invalid JSON' });
        }
    };


    if (isLoading) return <Spinner />;

    return (
        <div className="smc-quiz-editor" style={{ maxWidth: '100%', width: '100%' }}>
            <div className="smc-editor-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <Button isLink onClick={onBack}>&larr; {__('Back to List', 'smc-viable')}</Button>
                <div>
                    <Button isPrimary onClick={handleSave} isBusy={isSaving}>{__('Save Quiz', 'smc-viable')}</Button>
                </div>
            </div>

            <h2 style={{ marginTop: 0 }}>{quizId ? __('Edit Quiz', 'smc-viable') : __('Create Quiz', 'smc-viable')}</h2>

            <TextControl
                label={__('Quiz Title', 'smc-viable')}
                value={title}
                onChange={setTitle}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
            />

            <TabPanel
                className="my-tab-panel"
                activeClass="is-active"
                tabs={[
                    { name: 'questions', title: __('Questions', 'smc-viable'), className: 'tab-questions' },
                    { name: 'config', title: __('Configuration', 'smc-viable'), className: 'tab-config' },
                    { name: 'dashboard', title: __('Dashboard Logic', 'smc-viable'), className: 'tab-dashboard' },
                ]}
            >
                {(tab) => {
                    if (tab.name === 'questions') {
                        return (
                            <div style={{ marginTop: '20px' }}>
                                {questions.map((q, index) => (
                                    <QuestionEditor
                                        key={index}
                                        question={q}
                                        stages={stages}
                                        onChange={(newQ) => updateQuestion(index, newQ)}
                                        onRemove={() => removeQuestion(index)}
                                    />
                                ))}
                                <Button isSecondary onClick={addQuestion} style={{ marginTop: '20px' }}>{__('Add Question', 'smc-viable')}</Button>
                            </div>
                        );
                    } else if (tab.name === 'config') {
                        return (
                            <div style={{ marginTop: '20px' }}>
                                <h3>{__('Report Settings', 'smc-viable')}</h3>
                                <RadioControl
                                    label={__('Delivery Mode', 'smc-viable')}
                                    selected={settings.delivery_mode}
                                    options={[
                                        { label: 'Direct Download (PDF)', value: 'download' },
                                        { label: 'Email Report (collect Name/Email/Phone)', value: 'email' },
                                    ]}
                                    onChange={(val) => setSettings({ ...settings, delivery_mode: val })}
                                />
                                <hr style={{ margin: '20px 0' }} />

                                <h3>{__('Stages / Categories', 'smc-viable')}</h3>
                                <p className="description">{__('Define the stages used to group questions.', 'smc-viable')}</p>
                                {stages.map((stage, idx) => (
                                    <div key={idx} style={{ display: 'flex', gap: '10px', marginBottom: '10px' }}>
                                        <TextControl
                                            value={stage}
                                            onChange={(val) => updateStage(idx, val)}
                                            style={{ marginBottom: 0, flexGrow: 1 }}
                                            __next40pxDefaultSize
                                            __nextHasNoMarginBottom
                                        />
                                        <Button isDestructive variant="link" onClick={() => removeStage(idx)}>X</Button>
                                    </div>
                                ))}
                                <Button isSecondary onClick={addStage} isSmall>{__('Add Stage', 'smc-viable')}</Button>
                            </div>
                        );
                    } else if (tab.name === 'dashboard') {
                        return (
                            <div style={{ marginTop: '20px' }}>
                                <div className="flex justify-between items-center mb-4">
                                    <h3>{__('Scoring Rules', 'smc-viable')}</h3>
                                    <div className="flex gap-2">
                                        <Button isSecondary isSmall onClick={() => {
                                            const input = document.createElement('input');
                                            input.type = 'file';
                                            input.accept = 'application/json';
                                            input.onchange = (e) => {
                                                const file = e.target.files[0];
                                                const reader = new FileReader();
                                                reader.onload = (ev) => handleImportJson(ev.target.result);
                                                reader.readAsText(file);
                                            };
                                            input.click();
                                        }}>Import JSON</Button>
                                        <ToggleControl
                                            label="Advanced (Raw JSON)"
                                            checked={showJsonEditor}
                                            onChange={setShowJsonEditor}
                                        />
                                    </div>
                                </div>

                                <TextControl
                                    label={__('Report Title', 'smc-viable')}
                                    value={dashboardTitle}
                                    onChange={setDashboardTitle}
                                    help="The title shown on the final results page/PDF."
                                />

                                {showJsonEditor ? (
                                    <TextareaControl
                                        label="Raw JSON Config"
                                        value={JSON.stringify({ version: "1.0.0", dashboard_config: { title: dashboardTitle, rules: dashboardRules } }, null, 2)}
                                        onChange={(val) => handleImportJson(val)}
                                        rows={15}
                                    />
                                ) : (
                                    <div className="mt-6">
                                        {dashboardRules.length === 0 && <p className="text-gray-500 italic">No rules defined. Add one below.</p>}
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
                    }
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
