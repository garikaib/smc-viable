import { useState, useEffect } from '@wordpress/element';
import { ArrowLeft, Save, Play, FileText } from 'lucide-react';

export default function LessonEditor({ lessonId, onBack, onSaveSuccess }) {
    const [lesson, setLesson] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const fetchLesson = async () => {
            try {
                const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/lessons/${lessonId}`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                const data = await response.json();
                setLesson(data);
            } catch (err) {
                console.error("Failed to fetch lesson", err);
            } finally {
                setLoading(false);
            }
        };

        fetchLesson();
    }, [lessonId]);

    const handleSave = async () => {
        setSaving(true);
        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/lessons/${lessonId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify(lesson)
            });

            if (response.ok) {
                onSaveSuccess();
                onBack();
            }
        } catch (err) {
            console.error("Failed to save lesson", err);
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div>Loading lesson details...</div>;

    return (
        <div className="smc-lesson-editor">
            <header className="smc-editor-header">
                <button onClick={onBack} className="smc-btn-back">
                    <ArrowLeft size={18} /> Back to Structure
                </button>
                <div className="smc-editor-title-group">
                    <h2>Edit Lesson: {lesson.title}</h2>
                    <button
                        className="smc-btn-primary"
                        onClick={handleSave}
                        disabled={saving}
                    >
                        <Save size={18} /> {saving ? 'Saving...' : 'Save Lesson'}
                    </button>
                </div>
            </header>

            <div className="smc-editor-form">
                <div className="smc-form-group">
                    <label>Lesson Title</label>
                    <input
                        type="text"
                        value={lesson.title}
                        onChange={(e) => setLesson({ ...lesson, title: e.target.value })}
                    />
                </div>

                <div className="smc-form-row">
                    <div className="smc-form-group">
                        <label>Lesson Type</label>
                        <select
                            value={lesson.type}
                            onChange={(e) => setLesson({ ...lesson, type: e.target.value })}
                        >
                            <option value="video">Video Lesson</option>
                            <option value="text">Text/Article</option>
                        </select>
                    </div>
                    <div className="smc-form-group">
                        <label>Duration (minutes)</label>
                        <input
                            type="number"
                            value={lesson.duration}
                            onChange={(e) => setLesson({ ...lesson, duration: parseInt(e.target.value) })}
                        />
                    </div>
                </div>

                {lesson.type === 'video' && (
                    <div className="smc-form-group">
                        <label>Video URL (YouTube/Vimeo)</label>
                        <div className="smc-input-with-icon">
                            <Play size={18} />
                            <input
                                type="text"
                                placeholder="https://youtube.com/watch?v=..."
                                value={lesson.video_url}
                                onChange={(e) => setLesson({ ...lesson, video_url: e.target.value })}
                            />
                        </div>
                    </div>
                )}

                <div className="smc-form-group">
                    <label>Lesson Content</label>
                    <textarea
                        rows="15"
                        value={lesson.content}
                        onChange={(e) => setLesson({ ...lesson, content: e.target.value })}
                        placeholder="Raw HTML or Text content..."
                    ></textarea>
                </div>
            </div>
        </div>
    );
}
