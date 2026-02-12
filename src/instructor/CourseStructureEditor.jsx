import { useState, useEffect, useRef } from '@wordpress/element';
import { ArrowLeft, Plus, GripVertical, Trash2, Edit, X, Search, ChevronUp, ChevronDown, CheckCircle2, AlertCircle, Save, Video } from 'lucide-react';

export default function CourseStructureEditor({ course, onBack }) {
    const [sections, setSections] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [courseTitle, setCourseTitle] = useState(course.title || '');
    const [initialCourseTitle, setInitialCourseTitle] = useState(course.title || '');

    // Lesson Search State
    const [isSearching, setIsSearching] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [activeSectionIndex, setActiveSectionIndex] = useState(null);
    const [toast, setToast] = useState(null);

    // Create lesson state
    const [isCreatingLesson, setIsCreatingLesson] = useState(false);
    const [newLessonTitle, setNewLessonTitle] = useState('');
    const [newLessonType, setNewLessonType] = useState('video');
    const [newLessonVideo, setNewLessonVideo] = useState('');
    const [newLessonCaption, setNewLessonCaption] = useState('');
    const [newLessonDuration, setNewLessonDuration] = useState('');

    // Video lesson inline editor
    const [editingVideoLesson, setEditingVideoLesson] = useState(null);
    const [videoEditorSaving, setVideoEditorSaving] = useState(false);

    const toastTimerRef = useRef(null);

    const showToast = (status, text) => {
        setToast({ status, text });
        if (toastTimerRef.current) {
            clearTimeout(toastTimerRef.current);
        }
        toastTimerRef.current = setTimeout(() => setToast(null), 2800);
    };

    useEffect(() => {
        const fetchStructure = async () => {
            try {
                const response = await fetch(`${wpApiSettings.root}smc/v1/courses/${course.id}/structure`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                const data = await response.json();
                setSections(data.sections || []);
                const resolvedTitle = data.title || course.title || '';
                setCourseTitle(resolvedTitle);
                setInitialCourseTitle(resolvedTitle);
            } catch (err) {
                console.error('Failed to fetch structure', err);
            } finally {
                setLoading(false);
            }
        };

        fetchStructure();
        return () => {
            if (toastTimerRef.current) {
                clearTimeout(toastTimerRef.current);
            }
        };
    }, [course.id, course.title]);

    const handleSave = async () => {
        const trimmedTitle = String(courseTitle || '').trim();
        if (!trimmedTitle) {
            showToast('error', 'Course title cannot be empty.');
            return;
        }

        setSaving(true);
        try {
            let titleSaved = true;
            if (trimmedTitle !== String(initialCourseTitle || '').trim()) {
                const titleResponse = await fetch(`${wpApiSettings.root}smc/v1/instructor/courses/${course.id}/title`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings.nonce
                    },
                    body: JSON.stringify({ title: trimmedTitle })
                });

                if (titleResponse.ok) {
                    setInitialCourseTitle(trimmedTitle);
                } else {
                    titleSaved = false;
                }
            }

            const dataToSave = sections.map((section) => ({
                title: section.title,
                lessons: (section.lessons || []).map((lesson) => lesson.id)
            }));

            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/courses/${course.id}/structure`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({ sections: dataToSave })
            });

            if (response.ok) {
                showToast(titleSaved ? 'success' : 'error', titleSaved ? 'Saved successfully.' : 'Structure saved, but title could not be updated.');
            } else {
                showToast('error', 'Save failed. Please try again.');
            }
        } catch (err) {
            console.error('Failed to save structure', err);
            showToast('error', 'Save failed. Please try again.');
        } finally {
            setSaving(false);
        }
    };

    const addSection = () => {
        setSections([...sections, { title: 'New Section', lessons: [] }]);
    };

    const removeSection = (index) => {
        if (confirm('Are you sure you want to delete this section?')) {
            const newSections = [...sections];
            newSections.splice(index, 1);
            setSections(newSections);
        }
    };

    const openSearch = (sectionIndex) => {
        setActiveSectionIndex(sectionIndex);
        setSearchQuery('');
        setSearchResults([]);
        setIsSearching(true);
    };

    const searchLessons = async (query) => {
        setSearchQuery(query);
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/lessons/search?q=${encodeURIComponent(query)}`, {
                headers: { 'X-WP-Nonce': wpApiSettings.nonce }
            });
            const data = await response.json();
            setSearchResults(Array.isArray(data) ? data : []);
        } catch (err) {
            console.error('Search failed', err);
        }
    };

    const attachLesson = (lesson) => {
        const newSections = [...sections];

        newSections[activeSectionIndex].lessons.push({
            id: lesson.id,
            title: lesson.title,
            type: lesson.type,
            duration: Number(lesson.duration || 0),
            video_url: lesson.video_url || '',
            video_caption: lesson.video_caption || '',
            embed_settings: lesson.embed_settings || {
                autoplay: false,
                loop: false,
                muted: false,
                controls: true,
            }
        });

        setSections(newSections);
        setIsSearching(false);
    };

    const createNewLesson = async () => {
        const title = newLessonTitle.trim();
        if (!title) {
            return;
        }

        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/lessons`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    title,
                    type: newLessonType,
                    video_url: newLessonVideo,
                    video_caption: newLessonCaption,
                    duration: newLessonDuration,
                    embed_settings: {
                        autoplay: false,
                        loop: false,
                        muted: false,
                        controls: true,
                    }
                })
            });

            if (response.ok) {
                const newLesson = await response.json();
                attachLesson(newLesson);
                setIsCreatingLesson(false);
                setNewLessonTitle('');
                setNewLessonVideo('');
                setNewLessonCaption('');
                setNewLessonDuration('');
                showToast('success', 'Lesson created and attached.');
            } else {
                showToast('error', 'Could not create lesson.');
            }
        } catch (err) {
            console.error('Failed to create lesson', err);
            showToast('error', 'Could not create lesson.');
        }
    };

    const removeLesson = (sectionIndex, lessonIndex) => {
        const newSections = [...sections];
        newSections[sectionIndex].lessons.splice(lessonIndex, 1);
        setSections(newSections);
    };

    const moveLesson = (sectionIndex, lessonIndex, direction) => {
        const newSections = [...sections];
        const lessons = newSections[sectionIndex].lessons;

        if (direction === 'up' && lessonIndex > 0) {
            [lessons[lessonIndex], lessons[lessonIndex - 1]] = [lessons[lessonIndex - 1], lessons[lessonIndex]];
        } else if (direction === 'down' && lessonIndex < lessons.length - 1) {
            [lessons[lessonIndex], lessons[lessonIndex + 1]] = [lessons[lessonIndex + 1], lessons[lessonIndex]];
        }

        setSections(newSections);
    };

    const openVideoEditor = (lesson) => {
        setEditingVideoLesson({
            id: lesson.id,
            title: lesson.title || '',
            video_url: lesson.video_url || '',
            video_caption: lesson.video_caption || '',
            duration: lesson.duration || '',
            embed_settings: {
                autoplay: Boolean(lesson?.embed_settings?.autoplay),
                loop: Boolean(lesson?.embed_settings?.loop),
                muted: Boolean(lesson?.embed_settings?.muted),
                controls: lesson?.embed_settings?.controls !== false,
            }
        });
    };

    const saveVideoEditor = async () => {
        if (!editingVideoLesson) {
            return;
        }

        setVideoEditorSaving(true);
        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/lessons/${editingVideoLesson.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    title: editingVideoLesson.title,
                    type: 'video',
                    video_url: editingVideoLesson.video_url,
                    video_caption: editingVideoLesson.video_caption,
                    duration: editingVideoLesson.duration,
                    embed_settings: editingVideoLesson.embed_settings,
                })
            });

            if (!response.ok) {
                showToast('error', 'Could not save video settings.');
                return;
            }

            setSections((prevSections) => prevSections.map((section) => ({
                ...section,
                lessons: (section.lessons || []).map((lesson) => lesson.id === editingVideoLesson.id
                    ? {
                        ...lesson,
                        title: editingVideoLesson.title,
                        type: 'video',
                        video_url: editingVideoLesson.video_url,
                        video_caption: editingVideoLesson.video_caption,
                        duration: Number(editingVideoLesson.duration || 0),
                        embed_settings: { ...editingVideoLesson.embed_settings },
                    }
                    : lesson),
            })));

            setEditingVideoLesson(null);
            showToast('success', 'Video settings saved.');
        } catch (err) {
            console.error('Failed to save video settings', err);
            showToast('error', 'Could not save video settings.');
        } finally {
            setVideoEditorSaving(false);
        }
    };

    if (loading) {
        return <div>Loading structure...</div>;
    }

    return (
        <div className="smc-structure-editor relative">
            <header className="smc-editor-header flex justify-between items-center mb-6">
                <div className="flex items-center gap-4 flex-1">
                    <button onClick={onBack} className="smc-btn-secondary">
                        <ArrowLeft size={18} className="mr-2" /> Back
                    </button>
                    <div className="smc-course-title-editor flex items-center gap-2 flex-1 max-w-3xl">
                        <input
                            type="text"
                            className="smc-course-title-input"
                            value={courseTitle}
                            onChange={(e) => setCourseTitle(e.target.value)}
                            aria-label="Course title"
                        />
                    </div>
                </div>
                <button
                    className="smc-btn-primary"
                    onClick={handleSave}
                    disabled={saving}
                >
                    <Save size={18} className="mr-2" />
                    {saving ? 'Saving...' : 'Save Changes'}
                </button>
            </header>

            <div className="smc-sections-container space-y-6">
                {sections.map((section, sIndex) => (
                    <div key={sIndex} className="bg-base-200 rounded-lg p-4 border border-base-content/10">
                        <div className="flex justify-between items-center mb-4">
                            <div className="flex items-center gap-3 flex-1">
                                <GripVertical className="text-base-content/30 cursor-move" size={20} />
                                <input
                                    type="text"
                                    className="bg-transparent text-lg font-bold text-base-content border-b border-transparent focus:border-primary focus:outline-none w-full"
                                    value={section.title}
                                    onChange={(e) => {
                                        const newSections = [...sections];
                                        newSections[sIndex].title = e.target.value;
                                        setSections(newSections);
                                    }}
                                />
                            </div>
                            <button
                                className="text-red-400 hover:text-red-300 ml-4"
                                title="Delete Section"
                                onClick={() => removeSection(sIndex)}
                            >
                                <Trash2 size={18} />
                            </button>
                        </div>

                        <div className="space-y-2 pl-4 border-l-2 border-base-content/10 ml-2">
                            {section.lessons.map((lesson, lIndex) => (
                                <div key={lesson.id} className="bg-base-300 p-3 rounded flex justify-between items-center group">
                                    <div className="flex items-center gap-3">
                                        <div className="flex flex-col">
                                            <button onClick={() => moveLesson(sIndex, lIndex, 'up')} className="text-base-content/30 hover:text-base-content disabled:opacity-30" disabled={lIndex === 0}>
                                                <ChevronUp size={12} />
                                            </button>
                                            <button onClick={() => moveLesson(sIndex, lIndex, 'down')} className="text-base-content/30 hover:text-base-content disabled:opacity-30" disabled={lIndex === section.lessons.length - 1}>
                                                <ChevronDown size={12} />
                                            </button>
                                        </div>
                                        <span className="text-base-content/40 text-sm w-6">
                                            {lesson.type === 'video' ? '🎥' : '📄'}
                                        </span>
                                        <span className="text-base-content font-medium">{lesson.title}</span>
                                    </div>

                                    <div className="smc-lesson-actions opacity-0 group-hover:opacity-100 transition-opacity">
                                        {lesson.type === 'video' ? (
                                            <button
                                                className="smc-lesson-action-btn smc-lesson-action-edit"
                                                title="Edit video settings"
                                                onClick={() => openVideoEditor(lesson)}
                                            >
                                                <Video size={12} className="mr-1" /> Video Settings
                                            </button>
                                        ) : (
                                            <a
                                                href={`/wp-admin/post.php?post=${lesson.id}&action=edit`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="smc-lesson-action-btn smc-lesson-action-edit"
                                                title="Edit content in Gutenberg"
                                            >
                                                <Edit size={12} className="mr-1" /> Edit
                                            </a>
                                        )}
                                        <button
                                            className="smc-lesson-action-btn smc-lesson-action-delete"
                                            title="Remove Lesson"
                                            onClick={() => removeLesson(sIndex, lIndex)}
                                        >
                                            <Trash2 size={14} />
                                        </button>
                                    </div>
                                </div>
                            ))}

                            <button
                                className="w-full py-2 border border-dashed border-base-content/20 text-base-content/40 rounded hover:border-base-content/40 hover:text-base-content transition-colors mt-2"
                                onClick={() => openSearch(sIndex)}
                            >
                                <Plus size={16} className="inline mr-1" /> Add Lesson
                            </button>
                        </div>
                    </div>
                ))}

                <button
                    className="w-full py-4 bg-base-200 rounded-lg text-base-content/40 hover:bg-base-300 hover:text-base-content transition-colors font-bold border border-dashed border-base-content/20"
                    onClick={addSection}
                >
                    <Plus size={20} className="inline mr-2" /> Add New Section
                </button>
            </div>

            {isSearching && (
                <div className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
                    <div className="bg-base-100 w-full max-w-lg rounded-2xl border border-base-content/10 shadow-2xl overflow-hidden backdrop-blur-xl">
                        <div className="p-6 border-b border-base-content/5 flex justify-between items-center bg-base-100/50">
                            <h3 className="font-bold text-lg text-base-content">Add Lesson to Section</h3>
                            <button onClick={() => setIsSearching(false)} className="text-base-content/40 hover:text-base-content">
                                <X size={20} />
                            </button>
                        </div>

                        <div className="p-4">
                            <div className="relative mb-4">
                                <Search className="absolute left-3 top-3 text-base-content/30" size={18} />
                                <input
                                    type="text"
                                    className="w-full bg-base-200 border border-base-content/10 rounded-lg py-2.5 pl-10 pr-4 text-base-content focus:outline-none focus:border-teal-500 transition-all font-medium"
                                    placeholder="Search existing lessons..."
                                    value={searchQuery}
                                    onChange={(e) => searchLessons(e.target.value)}
                                    autoFocus
                                />
                            </div>

                            <div className="max-h-60 overflow-y-auto space-y-2 mb-4">
                                {searchResults.map((result) => (
                                    <div
                                        key={result.id}
                                        className="p-3 bg-base-200 hover:bg-teal-500/10 hover:text-teal-500 cursor-pointer rounded-lg flex justify-between items-center transition-all"
                                        onClick={() => attachLesson(result)}
                                    >
                                        <span className="font-medium">{result.title}</span>
                                        <span className="text-xs bg-base-content/10 px-2 py-1 rounded text-base-content/60">{result.type}</span>
                                    </div>
                                ))}
                                {searchQuery.length > 2 && searchResults.length === 0 && (
                                    <div className="text-gray-500 text-center py-4">No lessons found.</div>
                                )}
                            </div>

                            <div className="pt-4 border-t border-base-content/5">
                                {!isCreatingLesson ? (
                                    <button
                                        className="w-full py-2 bg-primary text-white rounded font-bold hover:brightness-110"
                                        onClick={() => {
                                            setIsCreatingLesson(true);
                                            setNewLessonTitle('');
                                            setNewLessonVideo('');
                                            setNewLessonCaption('');
                                            setNewLessonDuration('');
                                        }}
                                    >
                                        <Plus size={16} className="inline mr-1" /> Create New Lesson
                                    </button>
                                ) : (
                                    <div className="space-y-3">
                                        <input
                                            type="text"
                                            className="w-full bg-base-200 border border-base-content/10 rounded-lg py-2.5 px-3 text-base-content focus:outline-none focus:border-teal-500 transition-all font-medium"
                                            placeholder="Lesson title"
                                            value={newLessonTitle}
                                            onChange={(e) => setNewLessonTitle(e.target.value)}
                                            autoFocus
                                        />

                                        <div className="grid grid-cols-2 gap-3">
                                            <select
                                                className="bg-base-200 border border-base-content/10 rounded-lg py-2.5 px-3 text-base-content focus:outline-none focus:border-teal-500"
                                                value={newLessonType}
                                                onChange={(e) => setNewLessonType(e.target.value)}
                                            >
                                                <option value="video">Video Lesson</option>
                                                <option value="text">Text / Article</option>
                                            </select>
                                            <input
                                                type="number"
                                                className="bg-base-200 border border-base-content/10 rounded-lg py-2.5 px-3 text-base-content focus:outline-none focus:border-teal-500"
                                                placeholder="Duration (min)"
                                                value={newLessonDuration}
                                                onChange={(e) => setNewLessonDuration(e.target.value)}
                                            />
                                        </div>

                                        {newLessonType === 'video' && (
                                            <>
                                                <input
                                                    type="text"
                                                    className="w-full bg-base-200 border border-base-content/10 rounded-lg py-2.5 px-3 text-base-content focus:outline-none focus:border-teal-500 transition-all font-medium"
                                                    placeholder="Video URL (YouTube/Vimeo)"
                                                    value={newLessonVideo}
                                                    onChange={(e) => setNewLessonVideo(e.target.value)}
                                                />
                                                <input
                                                    type="text"
                                                    className="w-full bg-base-200 border border-base-content/10 rounded-lg py-2.5 px-3 text-base-content focus:outline-none focus:border-teal-500 transition-all font-medium"
                                                    placeholder="Fallback caption (optional)"
                                                    value={newLessonCaption}
                                                    onChange={(e) => setNewLessonCaption(e.target.value)}
                                                />
                                            </>
                                        )}

                                        <div className="flex gap-2">
                                            <button
                                                className="flex-1 py-2 bg-base-content/10 text-base-content rounded font-semibold hover:bg-base-content/15"
                                                onClick={() => {
                                                    setIsCreatingLesson(false);
                                                    setNewLessonTitle('');
                                                    setNewLessonVideo('');
                                                    setNewLessonCaption('');
                                                    setNewLessonDuration('');
                                                }}
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                className="flex-1 py-2 bg-primary text-white rounded font-bold hover:brightness-110 disabled:opacity-60"
                                                onClick={createNewLesson}
                                                disabled={!newLessonTitle.trim()}
                                            >
                                                Create
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {editingVideoLesson && (
                <div className="smc-modal-overlay smc-course-modal-overlay">
                    <div className="smc-modal smc-course-modal">
                        <div className="flex justify-between items-start gap-4 mb-3">
                            <div>
                                <h3>Video Lesson Settings</h3>
                                <p className="smc-course-modal-subtitle">Edit URL, caption and embed behavior for this video lesson.</p>
                            </div>
                            <button
                                type="button"
                                className="smc-invite-close-btn"
                                onClick={() => setEditingVideoLesson(null)}
                                aria-label="Close"
                            >
                                <X size={18} />
                            </button>
                        </div>

                        <div className="space-y-3">
                            <div className="smc-form-group">
                                <label>Lesson Title</label>
                                <input
                                    className="smc-invite-input"
                                    type="text"
                                    value={editingVideoLesson.title}
                                    onChange={(e) => setEditingVideoLesson({ ...editingVideoLesson, title: e.target.value })}
                                />
                            </div>

                            <div className="smc-form-group">
                                <label>Video URL</label>
                                <input
                                    className="smc-invite-input"
                                    type="text"
                                    placeholder="https://youtube.com/watch?v=..."
                                    value={editingVideoLesson.video_url}
                                    onChange={(e) => setEditingVideoLesson({ ...editingVideoLesson, video_url: e.target.value })}
                                />
                            </div>

                            <div className="smc-form-group">
                                <label>Fallback Caption</label>
                                <input
                                    className="smc-invite-input"
                                    type="text"
                                    placeholder="Shown below video in player"
                                    value={editingVideoLesson.video_caption}
                                    onChange={(e) => setEditingVideoLesson({ ...editingVideoLesson, video_caption: e.target.value })}
                                />
                            </div>

                            <div className="smc-form-group">
                                <label>Duration (minutes)</label>
                                <input
                                    className="smc-invite-input"
                                    type="number"
                                    min="0"
                                    value={editingVideoLesson.duration}
                                    onChange={(e) => setEditingVideoLesson({ ...editingVideoLesson, duration: e.target.value })}
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-2 smc-video-setting-grid">
                                <label className="smc-video-setting-item">
                                    <input
                                        type="checkbox"
                                        checked={editingVideoLesson.embed_settings.autoplay}
                                        onChange={(e) => setEditingVideoLesson({
                                            ...editingVideoLesson,
                                            embed_settings: {
                                                ...editingVideoLesson.embed_settings,
                                                autoplay: e.target.checked,
                                            }
                                        })}
                                    />
                                    <span>Autoplay</span>
                                </label>
                                <label className="smc-video-setting-item">
                                    <input
                                        type="checkbox"
                                        checked={editingVideoLesson.embed_settings.loop}
                                        onChange={(e) => setEditingVideoLesson({
                                            ...editingVideoLesson,
                                            embed_settings: {
                                                ...editingVideoLesson.embed_settings,
                                                loop: e.target.checked,
                                            }
                                        })}
                                    />
                                    <span>Loop</span>
                                </label>
                                <label className="smc-video-setting-item">
                                    <input
                                        type="checkbox"
                                        checked={editingVideoLesson.embed_settings.muted}
                                        onChange={(e) => setEditingVideoLesson({
                                            ...editingVideoLesson,
                                            embed_settings: {
                                                ...editingVideoLesson.embed_settings,
                                                muted: e.target.checked,
                                            }
                                        })}
                                    />
                                    <span>Muted</span>
                                </label>
                                <label className="smc-video-setting-item">
                                    <input
                                        type="checkbox"
                                        checked={editingVideoLesson.embed_settings.controls}
                                        onChange={(e) => setEditingVideoLesson({
                                            ...editingVideoLesson,
                                            embed_settings: {
                                                ...editingVideoLesson.embed_settings,
                                                controls: e.target.checked,
                                            }
                                        })}
                                    />
                                    <span>Show Controls</span>
                                </label>
                            </div>
                        </div>

                        <div className="smc-modal-actions">
                            <button type="button" className="smc-btn-secondary" onClick={() => setEditingVideoLesson(null)}>
                                Cancel
                            </button>
                            <button type="button" className="smc-btn-primary" disabled={videoEditorSaving} onClick={saveVideoEditor}>
                                <Save size={16} className="mr-1" /> {videoEditorSaving ? 'Saving...' : 'Save Video'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

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
