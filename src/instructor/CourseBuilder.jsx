import { useState, useEffect } from '@wordpress/element';
import { Plus, Edit, Trash, BookOpen } from 'lucide-react';
import CourseStructureEditor from './CourseStructureEditor';

export default function CourseBuilder() {
    const [courses, setCourses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [isCreating, setIsCreating] = useState(false);
    const [editingCourse, setEditingCourse] = useState(null);
    const [newCourseTitle, setNewCourseTitle] = useState('');

    const fetchCourses = async () => {
        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/courses`, {
                headers: { 'X-WP-Nonce': wpApiSettings.nonce }
            });
            const data = await response.json();
            setCourses(data);
        } catch (err) {
            console.error("Failed to fetch courses", err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchCourses();
    }, []);

    const handleCreateCourse = async (e) => {
        e.preventDefault();
        if (!newCourseTitle) return;

        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/courses`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({ title: newCourseTitle })
            });

            if (response.ok) {
                setNewCourseTitle('');
                setIsCreating(false);
                fetchCourses();
            }
        } catch (err) {
            console.error("Failed to create course", err);
        }
    };

    if (loading) return <div>Loading courses...</div>;

    if (editingCourse) {
        return (
            <CourseStructureEditor
                course={editingCourse}
                onBack={() => {
                    setEditingCourse(null);
                    fetchCourses();
                }}
            />
        );
    }

    return (
        <div className="smc-course-builder">
            <header className="smc-builder-header mb-10">
                <div className="smc-view-title text-left">
                    <span className="smc-premium-badge">CURRICULUM</span>
                    <h2 className="smc-premium-heading text-3xl mt-2">Modules</h2>
                </div>
                <button
                    className="smc-btn-primary"
                    onClick={() => setIsCreating(true)}
                    style={{ display: 'flex', alignItems: 'center', gap: '8px' }}
                >
                    <Plus size={18} /> NEW MODULE
                </button>
            </header>

            {isCreating && (
                <div className="smc-modal-overlay">
                    <div className="smc-modal">
                        <h3>Create New Course</h3>
                        <form onSubmit={handleCreateCourse}>
                            <input
                                type="text"
                                placeholder="Course Title"
                                value={newCourseTitle}
                                onChange={(e) => setNewCourseTitle(e.target.value)}
                                autoFocus
                            />
                            <div className="smc-modal-actions">
                                <button type="button" onClick={() => setIsCreating(false)} className="smc-btn-secondary">Cancel</button>
                                <button type="submit" className="smc-btn-primary">Create</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            <div className="smc-course-grid">
                {courses.length === 0 ? (
                    <div className="smc-empty-state">
                        <BookOpen size={48} />
                        <p>No courses found. Create your first course to get started!</p>
                    </div>
                ) : (
                    courses.map(course => (
                        <div key={course.id} className="smc-course-card">
                            <div className="smc-course-icon">🎓</div>
                            <div className="smc-course-info">
                                <h4>{course.title}</h4>
                                <span className="smc-course-badge">{course.status}</span>
                            </div>
                            <div className="smc-course-actions">
                                <button
                                    className="smc-btn-icon"
                                    title="Edit Structure"
                                    onClick={() => setEditingCourse(course)}
                                >
                                    <Edit size={18} />
                                </button>
                                <button className="smc-btn-icon smc-btn-danger" title="Delete">
                                    <Trash size={18} />
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>

        </div>
    );
}
