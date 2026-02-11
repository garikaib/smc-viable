import { useState, useEffect } from '@wordpress/element';
import { Plus, Edit, Trash, BookOpen, Users, Layout, Shield, Settings } from 'lucide-react';
import CourseStructureEditor from './CourseStructureEditor';
import QuizEnrollmentRules from './QuizEnrollmentRules';
import StudentManager from './StudentManager';

export default function CourseBuilder() {
    const [courses, setCourses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [view, setView] = useState('list'); // 'list', 'edit_structure', 'edit_rules', 'students'
    const [editingCourse, setEditingCourse] = useState(null);

    // Creation State
    const [isCreating, setIsCreating] = useState(false);
    const [newCourse, setNewCourse] = useState({ title: '', access_type: 'standalone', plan_level: 'free' });

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
        if (!newCourse.title) return;

        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/courses`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify(newCourse)
            });

            if (response.ok) {
                setNewCourse({ title: '', access_type: 'standalone', plan_level: 'free' });
                setIsCreating(false);
                fetchCourses();
            }
        } catch (err) {
            console.error("Failed to create course", err);
        }
    };

    if (loading) return <div>Loading courses...</div>;

    // View Routing
    if (view === 'edit_structure' && editingCourse) {
        return (
            <CourseStructureEditor
                course={editingCourse}
                onBack={() => {
                    setEditingCourse(null);
                    setView('list');
                    fetchCourses();
                }}
            />
        );
    }

    if (view === 'edit_rules') {
        return (
            <QuizEnrollmentRules
                onBack={() => setView('list')}
            />
        );
    }

    if (view === 'students') {
        return <StudentManager />;
    }

    return (
        <div className="smc-course-builder-content">
            <div className="flex justify-end gap-3 mb-6">
                <button
                    className="smc-btn-secondary"
                    onClick={() => setView('edit_rules')}
                >
                    <Shield size={18} className="mr-2" /> Enrollment Rules
                </button>
                <button
                    className="smc-btn-primary"
                    onClick={() => setIsCreating(true)}
                >
                    <Plus size={18} className="mr-2" /> New Course
                </button>
            </div>

            {isCreating && (
                <div className="smc-modal-overlay">
                    <div className="smc-modal">
                        <h3>Create New Course</h3>
                        <form onSubmit={handleCreateCourse}>
                            <div className="smc-form-group">
                                <label>Course Title</label>
                                <input
                                    type="text"
                                    placeholder="e.g. Advanced Copywriting"
                                    value={newCourse.title}
                                    onChange={(e) => setNewCourse({ ...newCourse, title: e.target.value })}
                                    autoFocus
                                />
                            </div>

                            <div className="smc-form-group">
                                <label>Access Type</label>
                                <select
                                    value={newCourse.access_type}
                                    onChange={(e) => setNewCourse({ ...newCourse, access_type: e.target.value })}
                                >
                                    <option value="standalone">Standalone (Purchase/Enroll)</option>
                                    <option value="plan">Plan Access (Membership)</option>
                                </select>
                            </div>

                            {newCourse.access_type === 'plan' && (
                                <div className="smc-form-group">
                                    <label>Minimum Plan Level</label>
                                    <select
                                        value={newCourse.plan_level}
                                        onChange={(e) => setNewCourse({ ...newCourse, plan_level: e.target.value })}
                                    >
                                        <option value="free">Free Tier</option>
                                        <option value="basic">Basic Plan</option>
                                        <option value="premium">Premium Plan</option>
                                    </select>
                                </div>
                            )}

                            <div className="smc-modal-actions">
                                <button type="button" onClick={() => setIsCreating(false)} className="smc-btn-secondary">Cancel</button>
                                <button type="submit" className="smc-btn-primary">Create</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {courses.length === 0 ? (
                    <div className="col-span-full flex flex-col items-center justify-center p-20 border border-dashed border-base-content/20 rounded-2xl bg-base-content/5">
                        <BookOpen size={48} className="text-base-content/30 mb-4" />
                        <h3 className="text-xl font-bold text-base-content mb-2">No courses found</h3>
                        <p className="text-base-content/60">Create your first course to get started!</p>
                    </div>
                ) : (
                    courses.map(course => (
                        <div key={course.id} className="group relative bg-base-200 border border-base-content/10 rounded-xl overflow-hidden hover:border-teal-500/50 transition-all duration-300 hover:shadow-2xl hover:shadow-teal-900/10">
                            {/* Card Image / Visual */}
                            <div className="h-48 bg-base-300 relative overflow-hidden">
                                {course.image ? (
                                    <img src={course.image} alt={course.title} className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" />
                                ) : (
                                    <div className="w-full h-full flex items-center justify-center bg-gradient-to-br from-base-300 to-base-200">
                                        <span className="text-4xl text-base-content/40">🎓</span>
                                    </div>
                                )}
                                <div className="absolute top-3 right-3">
                                    <span className={`px-2 py-1 text-xs font-bold uppercase tracking-wider rounded backdrop-blur-md ${course.status === 'publish' ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-500'}`}>
                                        {course.status}
                                    </span>
                                </div>
                            </div>

                            {/* Card Content */}
                            <div className="p-6">
                                <h4 className="text-lg font-bold text-base-content mb-3 line-clamp-2 min-h-[3.5rem]">{course.title}</h4>

                                <div className="flex flex-wrap gap-4 text-xs text-base-content/60 mb-6">
                                    <span className="flex items-center gap-1.5" title="Access Type">
                                        <Shield size={14} className="text-teal-500" />
                                        {course.access_type === 'plan' ? `Plan: ${course.plan_level}` : 'Standalone'}
                                    </span>
                                    <span className="flex items-center gap-1.5" title="Students">
                                        <Users size={14} className="text-blue-500" /> {course.students_count || 0} Students
                                    </span>
                                    <span className="flex items-center gap-1.5" title="Lessons">
                                        <Layout size={14} className="text-purple-500" /> {course.lessons_count || 0} Lessons
                                    </span>
                                </div>

                                <div className="flex items-center gap-3 mt-auto">
                                    <button
                                        className="flex-1 flex items-center justify-center gap-2 bg-base-content/5 hover:bg-base-content/10 text-base-content py-2.5 rounded-lg text-sm font-medium transition-colors border border-base-content/5"
                                        onClick={() => {
                                            setEditingCourse(course);
                                            setView('edit_structure');
                                        }}
                                    >
                                        <Edit size={16} /> Edit Content
                                    </button>
                                    <a
                                        href={`/wp-admin/post.php?post=${course.id}&action=edit`}
                                        target="_blank"
                                        className="p-2.5 rounded-lg bg-base-content/5 hover:bg-base-content/10 text-base-content/60 hover:text-base-content transition-colors border border-base-content/5"
                                        title="Edit Settings"
                                    >
                                        <Settings size={16} />
                                    </a>
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </div>

        </div>
    );
}
