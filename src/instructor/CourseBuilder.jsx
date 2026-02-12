import { useState, useEffect } from '@wordpress/element';
import { Plus, Edit, Trash, BookOpen, Users, Layout, Shield, Settings } from 'lucide-react';
import CourseStructureEditor from './CourseStructureEditor';
import QuizEnrollmentRules from './QuizEnrollmentRules';
import StudentManager from './StudentManager';

const PLAN_OPTIONS = Array.isArray(wpApiSettings?.planTiers) && wpApiSettings.planTiers.length
    ? wpApiSettings.planTiers
    : [
        { label: 'Free Plan', value: 'free' },
        { label: 'Basic', value: 'basic' },
        { label: 'Standard', value: 'standard' },
    ];
const PLAN_LABEL_BY_VALUE = PLAN_OPTIONS.reduce((acc, option) => {
    acc[option.value] = option.label;
    return acc;
}, {});

export default function CourseBuilder() {
    const [courses, setCourses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [view, setView] = useState('list'); // 'list', 'edit_structure', 'edit_rules', 'students'
    const [editingCourse, setEditingCourse] = useState(null);
    const [editingSettings, setEditingSettings] = useState(null);
    const [ownerFilter, setOwnerFilter] = useState('all'); // all | mine

    // Creation State
    const [isCreating, setIsCreating] = useState(false);
    const [newCourse, setNewCourse] = useState({ title: '', access_type: 'standalone', plan_level: 'free' });
    const currentUserId = wpApiSettings?.user?.id ? Number(wpApiSettings.user.id) : 0;

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

    const handleUpdateSettings = async (e) => {
        e.preventDefault();
        if (!editingSettings) return;

        try {
            // Prepare payload
            const payload = {
                id: editingSettings.id,
                title: editingSettings.title,
                description: editingSettings.content || '',
                access_type: editingSettings.access_type,
                plan_level: editingSettings.plan_level,
                status: editingSettings.status,
                price: editingSettings.price
            };

            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/courses`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify(payload)
            });

            if (response.ok) {
                setEditingSettings(null);
                fetchCourses();
            } else {
                console.error("Failed to update course");
            }
        } catch (err) {
            console.error("Error updating course", err);
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

    const visibleCourses = ownerFilter === 'mine' && currentUserId
        ? courses.filter(course => Number(course.creator_id) === currentUserId)
        : courses;

    return (
        <div className="smc-course-builder-content">
            <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-2 bg-base-200 border border-base-content/10 rounded-xl p-1">
                    <button
                        className={`px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors ${ownerFilter === 'all' ? 'bg-teal-500 text-white' : 'text-base-content/70 hover:text-base-content hover:bg-base-content/5'}`}
                        onClick={() => setOwnerFilter('all')}
                    >
                        All Courses
                    </button>
                    <button
                        className={`px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors ${ownerFilter === 'mine' ? 'bg-teal-500 text-white' : 'text-base-content/70 hover:text-base-content hover:bg-base-content/5'}`}
                        onClick={() => setOwnerFilter('mine')}
                    >
                        My Courses
                    </button>
                </div>

                <div className="flex items-center gap-3">
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
            </div>

            {isCreating && (
                <div className="smc-modal-overlay smc-course-modal-overlay">
                    <div className="smc-modal smc-course-modal">
                        <h3>Create New Course</h3>
                        <p className="smc-course-modal-subtitle">Set up a new course shell, then continue in the structure editor.</p>
                        <form onSubmit={handleCreateCourse}>
                            <div className="smc-form-group">
                                <label>Course Title</label>
                                <input
                                    className="smc-invite-input"
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
                                    className="smc-invite-input"
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
                                        className="smc-invite-input"
                                        value={newCourse.plan_level}
                                        onChange={(e) => setNewCourse({ ...newCourse, plan_level: e.target.value })}
                                    >
                                        {PLAN_OPTIONS.map((option) => (
                                            <option key={option.value} value={option.value}>{option.label}</option>
                                        ))}
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

            {/* Settings Modal */}
            {editingSettings && (
                <div className="smc-modal-overlay smc-course-modal-overlay">
                    <div className="smc-modal smc-course-modal">
                        <h3>Course Settings</h3>
                        <p className="smc-course-modal-subtitle">Update course details and publication status.</p>
                        <form onSubmit={handleUpdateSettings}>
                            <div className="smc-form-group">
                                <label>Course Title</label>
                                <input
                                    className="smc-invite-input"
                                    type="text"
                                    value={editingSettings.title}
                                    onChange={(e) => setEditingSettings({ ...editingSettings, title: e.target.value })}
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="smc-form-group">
                                    <label>Status</label>
                                    <select
                                        className="smc-invite-input"
                                        value={editingSettings.status}
                                        onChange={(e) => setEditingSettings({ ...editingSettings, status: e.target.value })}
                                    >
                                        <option value="draft">Draft</option>
                                        <option value="publish">Published</option>
                                        <option value="private">Private</option>
                                    </select>
                                </div>
                                <div className="smc-form-group">
                                    <label>Access Type</label>
                                    <select
                                        className="smc-invite-input"
                                        value={editingSettings.access_type}
                                        onChange={(e) => setEditingSettings({ ...editingSettings, access_type: e.target.value })}
                                    >
                                        <option value="standalone">Standalone</option>
                                        <option value="plan">Plan Access</option>
                                    </select>
                                </div>
                            </div>

                            {editingSettings.access_type === 'standalone' && (
                                <div className="smc-form-group">
                                    <label>Price ($) - <em>Linked Product will be updated</em></label>
                                    <input
                                        className="smc-invite-input"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={editingSettings.price ?? (editingSettings.linked_product?.price || '')}
                                        onChange={(e) => {
                                            const newPrice = e.target.value;
                                            setEditingSettings({
                                                ...editingSettings,
                                                price: newPrice
                                            });
                                        }}
                                        placeholder="0.00"
                                    />
                                </div>
                            )}

                            {editingSettings.access_type === 'plan' && (
                                <div className="smc-form-group">
                                    <label>Minimum Plan Level</label>
                                    <select
                                        className="smc-invite-input"
                                        value={editingSettings.plan_level}
                                        onChange={(e) => setEditingSettings({ ...editingSettings, plan_level: e.target.value })}
                                    >
                                        {PLAN_OPTIONS.map((option) => (
                                            <option key={option.value} value={option.value}>{option.label}</option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div className="smc-modal-actions">
                                <button type="button" onClick={() => setEditingSettings(null)} className="smc-btn-secondary">Cancel</button>
                                <button type="submit" className="smc-btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {visibleCourses.length === 0 ? (
                    <div className="col-span-full flex flex-col items-center justify-center p-20 border border-dashed border-base-content/20 rounded-2xl bg-base-content/5">
                        <BookOpen size={48} className="text-base-content/30 mb-4" />
                        <h3 className="text-xl font-bold text-base-content mb-2">No courses found</h3>
                        <p className="text-base-content/60">Create your first course to get started!</p>
                    </div>
                ) : (
                    visibleCourses.map(course => (
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
                                        {course.access_type === 'plan'
                                            ? `Plan: ${PLAN_LABEL_BY_VALUE[course.plan_level] || course.plan_level}`
                                            : 'Standalone'}
                                    </span>
                                    <span className="flex items-center gap-1.5" title="Students">
                                        <Users size={14} className="text-blue-500" /> {course.students_count || 0} Students
                                    </span>
                                    <span className="flex items-center gap-1.5" title="Lessons">
                                        <Layout size={14} className="text-purple-500" /> {course.lessons_count || 0} Lessons
                                    </span>
                                    <span className="flex items-center gap-1.5" title="Creator">
                                        <Edit size={14} className="text-amber-500" />
                                        {course.creator_name || 'Unknown'}
                                    </span>
                                </div>

                                <div className="flex items-center gap-3 mt-auto">
                                    <button
                                        className="flex-1 flex items-center justify-center gap-2 bg-primary text-white py-2.5 rounded-lg text-sm font-bold shadow-lg shadow-primary/20 hover:brightness-110 transition-all transform hover:-translate-y-0.5"
                                        onClick={() => {
                                            setEditingCourse(course);
                                            setView('edit_structure');
                                        }}
                                    >
                                        <Edit size={16} /> Edit Content
                                    </button>
                                    <a
                                        href={course.preview_url || `/learning/${course.slug || ''}/`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="p-2.5 rounded-lg bg-base-content/5 hover:bg-base-content/10 text-base-content/60 hover:text-base-content transition-colors border border-base-content/5"
                                        title="Preview as Student"
                                    >
                                        <BookOpen size={16} />
                                    </a>
                                    <button
                                        className="p-2.5 rounded-lg bg-base-content/5 hover:bg-base-content/10 text-base-content/60 hover:text-base-content transition-colors border border-base-content/5"
                                        title="Edit Settings"
                                        onClick={() => setEditingSettings(course)}
                                    >
                                        <Settings size={16} />
                                    </button>
                                </div>

                                {course.linked_product && (
                                    <div className="mt-3 pt-3 border-t border-base-content/5 flex justify-between items-center text-xs text-base-content/60">
                                        <span className="flex items-center gap-1.5">
                                            <Layout size={12} className="text-emerald-500" />
                                            Linked: <span className="font-medium text-base-content">{course.linked_product.title}</span>
                                        </span>
                                        <span className="font-bold text-base-content">{course.linked_product.formatted_price}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))
                )}
            </div>

        </div >
    );
}
