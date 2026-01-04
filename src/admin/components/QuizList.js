import { __ } from '@wordpress/i18n';
import { Button, Spinner, Notice } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { fetchQuizzes, exportQuizzes, importQuizzes, deleteQuiz } from '../utils/api';

export default function QuizList({ onEdit, onCreate }) {
    const [quizzes, setQuizzes] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isProcessing, setIsProcessing] = useState(false);
    const [notice, setNotice] = useState(null);
    const fileInputRef = useRef(null);

    const loadQuizzes = () => {
        setIsLoading(true);
        fetchQuizzes()
            .then(setQuizzes)
            .catch((err) => console.error(err))
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
            setNotice({ status: 'success', text: __('Quizzes exported successfully!', 'smc-viable') });
        } catch (err) {
            setNotice({ status: 'error', text: err.message });
        } finally {
            setIsProcessing(false);
        }
    };

    const handleImportClick = () => {
        fileInputRef.current?.click();
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
            setNotice({ status: 'success', text: __('Quizzes imported successfully!', 'smc-viable') });
            loadQuizzes();
        } catch (err) {
            setNotice({ status: 'error', text: err.message });
        } finally {
            setIsProcessing(false);
            e.target.value = ''; // Reset file input
        }
    };

    if (isLoading) {
        return <Spinner />;
    }

    return (
        <div className="smc-quiz-list">
            {notice && (
                <Notice status={notice.status} onRemove={() => setNotice(null)} isDismissible>
                    {notice.text}
                </Notice>
            )}

            <input
                type="file"
                accept=".json"
                ref={fileInputRef}
                style={{ display: 'none' }}
                onChange={handleFileChange}
            />

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <h2>{__('All Quizzes', 'smc-viable')}</h2>
                <div className="flex gap-2">
                    <Button isSecondary onClick={handleExport} isBusy={isProcessing} disabled={isProcessing || quizzes.length === 0}>
                        {__('Export JSON', 'smc-viable')}
                    </Button>
                    <Button isSecondary onClick={handleImportClick} isBusy={isProcessing} disabled={isProcessing}>
                        {__('Import JSON', 'smc-viable')}
                    </Button>
                    <Button isPrimary onClick={onCreate}>{__('Create New Quiz', 'smc-viable')}</Button>
                </div>
            </div>

            {quizzes.length === 0 ? (
                <p>{__('No quizzes found. Create one or import from JSON!', 'smc-viable')}</p>
            ) : (
                <table className="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>{__('Title', 'smc-viable')}</th>
                            <th>{__('Shortcode', 'smc-viable')}</th>
                            <th>{__('Date', 'smc-viable')}</th>
                            <th>{__('Actions', 'smc-viable')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {quizzes.map((quiz, index) => (
                            <tr key={quiz.id || index}>
                                <td>
                                    <strong>{quiz.title?.rendered || __('Untitled', 'smc-viable')}</strong>
                                </td>
                                <td>
                                    <code style={{ background: '#f0f0f1', padding: '2px 5px', borderRadius: '3px' }}>
                                        [smc_quiz id="{quiz.id}"]
                                    </code>
                                    <Button
                                        isSmall
                                        variant="secondary"
                                        style={{ marginLeft: '10px' }}
                                        onClick={() => {
                                            const textToCopy = `[smc_quiz id="${quiz.id}"]`;
                                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                                navigator.clipboard.writeText(textToCopy)
                                                    .then(() => {
                                                        setNotice({ status: 'success', text: __('Shortcode copied!', 'smc-viable') });
                                                    })
                                                    .catch(err => {
                                                        console.error('Failed to copy: ', err);
                                                        setNotice({ status: 'error', text: __('Failed to copy shortcode.', 'smc-viable') });
                                                    });
                                            } else {
                                                // Fallback for insecure contexts or older browsers
                                                const textArea = document.createElement("textarea");
                                                textArea.value = textToCopy;
                                                textArea.style.position = "fixed"; // Avoid scrolling to bottom
                                                document.body.appendChild(textArea);
                                                textArea.focus();
                                                textArea.select();
                                                try {
                                                    document.execCommand('copy');
                                                    setNotice({ status: 'success', text: __('Shortcode copied!', 'smc-viable') });
                                                } catch (err) {
                                                    console.error('Fallback copy failed: ', err);
                                                    setNotice({ status: 'error', text: __('Failed to copy shortcode.', 'smc-viable') });
                                                }
                                                document.body.removeChild(textArea);
                                            }

                                            // Clear notice after 3 seconds
                                            setTimeout(() => setNotice(null), 3000);
                                        }}
                                    >
                                        {__('Copy', 'smc-viable')}
                                    </Button>
                                </td>
                                <td>{quiz.date ? new Date(quiz.date).toLocaleDateString() : '-'}</td>
                                <td>
                                    <Button isLink onClick={() => onEdit(quiz.id)}>{__('Edit', 'smc-viable')}</Button>
                                    {' | '}
                                    <Button
                                        isLink
                                        isDestructive
                                        onClick={async () => {
                                            if (!confirm(__('Are you sure you want to delete this quiz?', 'smc-viable'))) return;
                                            setIsProcessing(true);
                                            try {
                                                await deleteQuiz(quiz.id);
                                                setNotice({ status: 'success', text: __('Quiz deleted!', 'smc-viable') });
                                                loadQuizzes();
                                            } catch (err) {
                                                setNotice({ status: 'error', text: err.message });
                                            } finally {
                                                setIsProcessing(false);
                                            }
                                        }}
                                    >
                                        {__('Delete', 'smc-viable')}
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
