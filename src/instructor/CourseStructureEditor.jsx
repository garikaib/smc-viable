import { useState, useEffect } from '@wordpress/element';
import { ArrowLeft, Plus, GripVertical, Trash2, Edit } from 'lucide-react';
import LessonEditor from './LessonEditor';

export default function CourseStructureEditor({ course, onBack }) {
    const [sections, setSections] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [editingLessonId, setEditingLessonId] = useState(null);

    useEffect(() => {
        const fetchStructure = async () => {
            try {
                const response = await fetch(`${wpApiSettings.root}smc/v1/courses/${course.id}/structure`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                const data = await response.json();
                // Map to a more editable format if needed, but the current structure is:
                // { id, title, sections: [ { title, lessons: [ { id, title, type, ... } ] } ] }
                // We mainly want to save IDs.
                setSections(data.sections || []);
            } catch (err) {
                console.error("Failed to fetch structure", err);
            } finally {
                setLoading(false);
            }
        };

        fetchStructure();
    }, [course.id]);

    const handleSave = async () => {
        setSaving(true);
        try {
            // Transform sections back to ID-only lessons for storage
            const dataToSave = sections.map(s => ({
                title: s.title,
                lessons: s.lessons.map(l => l.id)
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
                alert('Saved successfully!');
            }
        } catch (err) {
            console.error("Failed to save structure", err);
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

    const addLesson = async (sectionIndex) => {
        const title = prompt('Enter lesson title:');
        if (!title) return;

        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/lessons`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({ title, type: 'video' })
            });

            if (response.ok) {
                const newLesson = await response.json();
                const newSections = [...sections];
                newSections[sectionIndex].lessons.push(newLesson);
                setSections(newSections);
            }
        } catch (err) {
            console.error("Failed to add lesson", err);
        }
    };

    const removeLesson = (sectionIndex, lessonIndex) => {
        const newSections = [...sections];
        newSections[sectionIndex].lessons.splice(lessonIndex, 1);
        setSections(newSections);
    };

    if (loading) return <div>Loading structure...</div>;

    if (editingLessonId) {
        return (
            <LessonEditor
                lessonId={editingLessonId}
                onBack={() => setEditingLessonId(null)}
                onSaveSuccess={() => {
                    // Refresh structure to get updated titles if needed
                    // fetchStructure(); 
                }}
            />
        );
    }

    return (
        <div className="smc-structure-editor">
            <header className="smc-editor-header">
                <button onClick={onBack} className="smc-btn-back">
                    <ArrowLeft size={18} /> Back to Courses
                </button>
                <div className="smc-editor-title-group">
                    <h2>Editing: {course.title}</h2>
                    <button
                        className="smc-btn-primary"
                        onClick={handleSave}
                        disabled={saving}
                    >
                        {saving ? 'Saving...' : 'Save Changes'}
                    </button>
                </div>
            </header>

            <div className="smc-sections-list">
                {sections.map((section, sIndex) => (
                    <div key={sIndex} className="smc-section-item">
                        <div className="smc-section-header">
                            <GripVertical className="smc-drag-handle" size={20} />
                            <input
                                type="text"
                                value={section.title}
                                onChange={(e) => {
                                    const newSections = [...sections];
                                    newSections[sIndex].title = e.target.value;
                                    setSections(newSections);
                                }}
                            />
                            <button
                                className="smc-btn-icon smc-btn-danger"
                                title="Delete Section"
                                onClick={() => removeSection(sIndex)}
                            >
                                <Trash2 size={18} />
                            </button>
                        </div>
                        <div className="smc-lessons-list">
                            {section.lessons.map((lesson, lIndex) => (
                                <div key={lesson.id} className="smc-lesson-row">
                                    <GripVertical className="smc-drag-handle" size={16} />
                                    <span className="smc-lesson-type-icon">
                                        {lesson.type === 'video' ? '🎥' : '📄'}
                                    </span>
                                    <span className="smc-lesson-title">{lesson.title}</span>
                                    <div className="smc-lesson-actions">
                                        <button
                                            className="smc-btn-icon"
                                            title="Edit Content"
                                            onClick={() => setEditingLessonId(lesson.id)}
                                        >
                                            <Edit size={14} />
                                        </button>
                                        <button
                                            className="smc-btn-icon smc-btn-danger"
                                            title="Remove Lesson"
                                            onClick={() => removeLesson(sIndex, lIndex)}
                                        >
                                            <Trash2 size={14} />
                                        </button>
                                    </div>
                                </div>
                            ))}
                            <button
                                className="smc-btn-add-lesson"
                                onClick={() => addLesson(sIndex)}
                            >
                                <Plus size={14} /> Add Lesson
                            </button>
                        </div>
                    </div>
                ))}

                <button className="smc-btn-dashed" onClick={addSection}>
                    <Plus size={18} /> Add New Section
                </button>
            </div>
        </div>
    );
}
