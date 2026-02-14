import { __ } from '@wordpress/i18n';
import { Button, Spinner, Notice } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { Copy, Check, Pencil, Trash2 } from 'lucide-react';
import { fetchQuizzes, exportQuizzes, importQuizzes, deleteQuiz, migrateQuizzes } from '../utils/api';

export default function QuizList({ onEdit, onCreate }) {
    const [quizzes, setQuizzes] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isProcessing, setIsProcessing] = useState(false);
    const [notice, setNotice] = useState(null);
    const [copyToast, setCopyToast] = useState('');
    const [copiedQuizId, setCopiedQuizId] = useState(null);
    const fileInputRef = useRef(null);

    const loadQuizzes = () => {
        setIsLoading(true);
        fetchQuizzes()
            .then(setQuizzes)
            .catch((err) => setNotice({ status: 'error', text: err.message }))
            .finally(() => setIsLoading(false));
    };

    useEffect(() => {
        loadQuizzes();
    }, []);

    const handleExport = async () => {
        setIsProcessing(true);
        try {
            const data = await exportQuizzes();
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `smc-quizzes-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            setNotice({ status: 'success', text: __('Quizzes exported successfully.', 'smc-viable') });
        } catch (err) {
            setNotice({ status: 'error', text: err.message });
        } finally {
            setIsProcessing(false);
        }
    };

    const handleImportClick = () => fileInputRef.current?.click();

    const handleMigrate = async () => {
        setIsProcessing(true);
        try {
            const result = await migrateQuizzes();
            const updated = Number(result?.updated || 0);
            const skipped = Number(result?.skipped || 0);
            setNotice({
                status: 'success',
                text: __('Migration complete.', 'smc-viable') + ` Updated: ${updated}, Skipped: ${skipped}.`
            });
            loadQuizzes();
        } catch (err) {
            setNotice({ status: 'error', text: err.message });
        } finally {
            setIsProcessing(false);
        }
    };

    const handleFileChange = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setIsProcessing(true);
        try {
            const text = await file.text();
            const jsonData = JSON.parse(text);
            if (!jsonData.assessments || !Array.isArray(jsonData.assessments)) {
                throw new Error(__('Invalid JSON format. Expected "assessments" array.', 'smc-viable'));
            }
            await importQuizzes(jsonData);
            setNotice({ status: 'success', text: __('Quizzes imported successfully.', 'smc-viable') });
            loadQuizzes();
        } catch (err) {
            setNotice({ status: 'error', text: err.message });
        } finally {
            setIsProcessing(false);
            e.target.value = '';
        }
    };

    const copyShortcode = async (quizId) => {
        const textToCopy = `[smc_quiz id="${quizId}"]`;
        try {
            await navigator.clipboard.writeText(textToCopy);
            setCopiedQuizId(quizId);
            setCopyToast(__('Shortcode copied.', 'smc-viable'));
            setTimeout(() => setCopiedQuizId(null), 1200);
            setTimeout(() => setCopyToast(''), 2200);
        } catch (err) {
            setCopyToast(__('Could not copy shortcode.', 'smc-viable'));
            setTimeout(() => setCopyToast(''), 2200);
        }
    };

    if (isLoading) {
        return (
            <div className="smc-admin-loading">
                <Spinner />
            </div>
        );
    }

    return (
        <div className="smc-quiz-list smc-panel">
            {notice && (
                <Notice status={notice.status} onRemove={() => setNotice(null)} isDismissible>
                    {notice.text}
                </Notice>
            )}

            <input type="file" accept=".json" ref={fileInputRef} style={{ display: 'none' }} onChange={handleFileChange} />
            {copyToast && <div className="smc-copy-toast" role="status" aria-live="polite">{copyToast}</div>}

            <div className="smc-panel-head">
                <div>
                    <h2>{__('Assessment Library', 'smc-viable')}</h2>
                    <p>{__('Manage all quizzes, copy shortcodes, and launch visual editing in one place.', 'smc-viable')}</p>
                </div>
                <div className="smc-head-actions">
                    <Button className="smc-action-btn smc-action-btn--amber" isSecondary onClick={handleExport} isBusy={isProcessing} disabled={isProcessing || quizzes.length === 0}>
                        {__('Export JSON', 'smc-viable')}
                    </Button>
                    <Button className="smc-action-btn smc-action-btn--glass" isSecondary onClick={handleImportClick} isBusy={isProcessing} disabled={isProcessing}>
                        {__('Import JSON', 'smc-viable')}
                    </Button>
                    <Button className="smc-action-btn smc-action-btn--amber" isSecondary onClick={handleMigrate} isBusy={isProcessing} disabled={isProcessing || quizzes.length === 0}>
                        {__('Migrate Types', 'smc-viable')}
                    </Button>
                    <Button className="smc-action-btn smc-action-btn--teal" isPrimary onClick={onCreate}>
                        {__('Create Assessment', 'smc-viable')}
                    </Button>
                </div>
            </div>

            <div className="smc-library-toolbar">
                <div className="smc-library-stats">
                    <span>{quizzes.length} {__('total assessments', 'smc-viable')}</span>
                </div>
            </div>

            {quizzes.length === 0 ? (
                <div className="smc-empty-state">
                    <h3>{__('No assessments found', 'smc-viable')}</h3>
                    <p>{__('Create a new assessment to get started.', 'smc-viable')}</p>
                </div>
            ) : (
                <div className="smc-quiz-grid">
                    {quizzes.map((quiz, index) => (
                        <article key={quiz.id} className="smc-quiz-card" style={{ '--smc-stagger': index }}>
                            <header>
                                <h3>{quiz.title?.rendered || __('Untitled', 'smc-viable')}</h3>
                                <span>{quiz.date ? new Date(quiz.date).toLocaleDateString() : '-'}</span>
                            </header>

                            <div className="smc-shortcode-row">
                                <code>[smc_quiz id="{quiz.id}"]</code>
                                <span
                                    className="smc-shortcode-copy"
                                    role="button"
                                    tabIndex={0}
                                    aria-label={__('Copy shortcode', 'smc-viable')}
                                    title={__('Copy shortcode', 'smc-viable')}
                                    onClick={() => copyShortcode(quiz.id)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter' || event.key === ' ') {
                                            event.preventDefault();
                                            copyShortcode(quiz.id);
                                        }
                                    }}
                                >
                                    {copiedQuizId === quiz.id ? <Check size={16} /> : <Copy size={16} />}
                                </span>
                            </div>

                            <div className="smc-card-actions-row">
                                <button
                                    type="button"
                                    className="smc-card-icon-btn smc-card-icon-btn--edit"
                                    onClick={() => onEdit(quiz.id, { title: quiz.title?.rendered || '' })}
                                >
                                    <Pencil size={15} />
                                    <span>{__('Edit Assessment', 'smc-viable')}</span>
                                </button>
                                <button
                                    type="button"
                                    className="smc-card-icon-btn smc-card-icon-btn--delete"
                                    onClick={async () => {
                                        if (!confirm(__('Delete this assessment?', 'smc-viable'))) return;
                                        setIsProcessing(true);
                                        try {
                                            await deleteQuiz(quiz.id);
                                            setNotice({ status: 'success', text: __('Assessment deleted.', 'smc-viable') });
                                            loadQuizzes();
                                        } catch (err) {
                                            setNotice({ status: 'error', text: err.message });
                                        } finally {
                                            setIsProcessing(false);
                                        }
                                    }}
                                    disabled={isProcessing}
                                >
                                    <Trash2 size={15} />
                                    <span>{__('Delete', 'smc-viable')}</span>
                                </button>
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </div>
    );
}
