import { __ } from '@wordpress/i18n';
import { Button, TextControl, Spinner, Notice, TabPanel, TextareaControl, RadioControl, PanelBody, PanelRow } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { fetchQuiz, saveQuiz } from '../utils/api';
import QuestionEditor from './QuestionEditor';

export default function QuizEditor({ quizId, onBack }) {
    const [title, setTitle] = useState('');
    const [questions, setQuestions] = useState([]);

    // New Config States
    const [settings, setSettings] = useState({ delivery_mode: 'download' });
    const [headerImage, setHeaderImage] = useState(''); // Future proofing
    const [stages, setStages] = useState(['Market & Offering', 'Business Model', 'Execution']);
    const [dashboardConfig, setDashboardConfig] = useState('{\n  "version": "1.0.0",\n  "dashboard_config": {\n    "title": "Business Viability Assessment Results",\n    "rules": []\n  }\n}');

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
                    if (meta._smc_quiz_dashboard_config) {
                        // Ensure it's string for Textarea
                        const config = meta._smc_quiz_dashboard_config;
                        setDashboardConfig(typeof config === 'string' ? config : JSON.stringify(config, null, 2));
                    }
                    if (meta._smc_quiz_stages && Array.isArray(meta._smc_quiz_stages) && meta._smc_quiz_stages.length > 0) {
                        setStages(meta._smc_quiz_stages);
                    }
                })
                .catch((err) => setNotice({ status: 'error', text: err.message }))
                .finally(() => setIsLoading(false));
        }
    }, [quizId]);

    const handleSave = () => {
        setIsSaving(true);
        setNotice(null);

        // Validate JSON
        let parsedConfig = {};
        try {
            parsedConfig = JSON.parse(dashboardConfig);
        } catch (e) {
            setNotice({ status: 'error', text: __('Invalid JSON in Dashboard Config', 'smc-viable') });
            setIsSaving(false);
            return;
        }

        const data = {
            id: quizId,
            title: title || __('New Quiz', 'smc-viable'),
            status: 'publish',
            questions: questions,
            settings: settings,
            dashboard_config: parsedConfig, // API expects object or JSON depending on registration. Controller expects raw param, update_post_meta handles it. WP meta handles arrays/objects serialized.
            stages: stages
        };

        // Note: Controller expects simple params. Arrays/Objects passed via axios/apiFetch become JSON payload.
        // WP REST API handles them. 

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
                            <div style={{ marginTop: '20px', maxWidth: '600px' }}>
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
                                <h3>{__('Dashboard JSON Configuration', 'smc-viable')}</h3>
                                <p>{__('Configure scoring rules and messages.', 'smc-viable')}</p>
                                <TextareaControl
                                    label={__('JSON Config', 'smc-viable')}
                                    value={dashboardConfig}
                                    onChange={setDashboardConfig}
                                    rows={20}
                                    style={{ fontFamily: 'monospace', fontSize: '12px' }}
                                />
                                <Button isSecondary onClick={() => {
                                    const input = document.createElement('input');
                                    input.type = 'file';
                                    input.accept = 'application/json';
                                    input.onchange = (e) => {
                                        const file = e.target.files[0];
                                        const reader = new FileReader();
                                        reader.onload = (ev) => setDashboardConfig(ev.target.result);
                                        reader.readAsText(file);
                                    };
                                    input.click();
                                }}>{__('Import JSON', 'smc-viable')}</Button>
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
