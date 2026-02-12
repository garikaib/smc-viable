import { useState, useEffect } from '@wordpress/element';
import { BookOpen, NotebookPen, UserCircle2, Globe, X } from 'lucide-react';
import Sidebar from './Sidebar';
import VideoRenderer from './VideoRenderer';
import TextRenderer from './TextRenderer';
import NotesSidebar from './NotesSidebar';
// styles are now consolidated in style.scss

export default function CoursePlayer({ courseId, courseSlug, onExit }) {
    const [structure, setStructure] = useState(null);
    const [loading, setLoading] = useState(true);
    const [completing, setCompleting] = useState(false);
    const [activeLessonId, setActiveLessonId] = useState(null);
    const [showCompletionModal, setShowCompletionModal] = useState(false);
    const [showInstructorModal, setShowInstructorModal] = useState(false);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [mobileNotesOpen, setMobileNotesOpen] = useState(false);
    const resolvedCourseId = Number(structure?.id || courseId || 0);

    useEffect(() => {
        const fetchStructure = async () => {
            if (!courseId && !courseSlug) {
                setLoading(false);
                setStructure(null);
                return;
            }

            const courseLookup = courseId
                ? String(courseId)
                : `slug/${encodeURIComponent(String(courseSlug).trim())}`;

            try {
                const response = await fetch(`${wpApiSettings.root}smc/v1/courses/${courseLookup}/structure`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                const data = await response.json();
                setStructure(data);

                // Set first lesson as active if none selected
                if (data.sections && data.sections.length > 0) {
                    let found = false;
                    // Try to find first not_started or in_progress lesson
                    for (const section of data.sections) {
                        if (section.lessons) {
                            for (const lesson of section.lessons) {
                                if (lesson.status !== 'completed') {
                                    setActiveLessonId(lesson.id);
                                    found = true;
                                    break;
                                }
                            }
                        }
                        if (found) break;
                    }
                    // Fallback to first lesson if all completed
                    if (!found && data.sections[0].lessons && data.sections[0].lessons.length > 0) {
                        setActiveLessonId(data.sections[0].lessons[0].id);
                    }
                }
            } catch (err) {
                console.error("Failed to load course", err);
            } finally {
                setLoading(false);
            }
        };

        fetchStructure();
    }, [courseId, courseSlug]);

    const handleComplete = async () => {
        if (!activeLessonId || !resolvedCourseId) return;
        setCompleting(true);

        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/student/progress/complete-lesson`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    course_id: resolvedCourseId,
                    lesson_id: activeLessonId
                })
            });
            const payload = await response.json();
            if (!response.ok || payload?.success !== true) {
                throw new Error(payload?.message || 'Could not save lesson progress');
            }

            // Update local state to mark as completed
            setStructure(prev => {
                const newSections = prev.sections.map(section => ({
                    ...section,
                    lessons: section.lessons.map(lesson =>
                        lesson.id === activeLessonId ? { ...lesson, status: 'completed' } : lesson
                    )
                }));
                return {
                    ...prev,
                    sections: newSections,
                    progress: Number.isFinite(payload?.progress) ? payload.progress : prev.progress
                };
            });

            // Find next lesson
            const allLessons = structure.sections.flatMap(s => s.lessons);
            const currentIndex = allLessons.findIndex(l => l.id === activeLessonId);

            if (currentIndex !== -1 && currentIndex < allLessons.length - 1) {
                setActiveLessonId(allLessons[currentIndex + 1].id);
            } else {
                setShowCompletionModal(true);
            }

        } catch (err) {
            console.error("Failed to complete lesson", err);
        } finally {
            setCompleting(false);
        }
    };

    if (loading) return <div className="smc-loading">Loading Course...</div>;
    if (!structure) return <div className="smc-error">Course not found.</div>;

    const activeLesson = structure.sections
        .flatMap(s => s.lessons)
        .find(l => l.id === activeLessonId);
    const instructor = structure?.instructor_profile || null;
    const socialEntries = instructor?.social_links
        ? Object.entries(instructor.social_links).filter(([, url]) => Boolean(url))
        : [];

    return (
        <div className="smc-course-player">
            {(mobileNavOpen || mobileNotesOpen) && (
                <button
                    type="button"
                    className="smc-mobile-overlay"
                    aria-label="Close panel"
                    onClick={() => {
                        setMobileNavOpen(false);
                        setMobileNotesOpen(false);
                    }}
                />
            )}
            <Sidebar
                title={structure.title}
                sections={structure.sections}
                activeLessonId={activeLessonId}
                onSelectLesson={(lessonId) => {
                    setActiveLessonId(lessonId);
                    setMobileNavOpen(false);
                }}
                onExit={onExit}
                progress={structure.progress || 0}
                className={mobileNavOpen ? 'is-mobile-open' : ''}
            />
            <div className="smc-player-content">
                <header className="smc-player-header">
                    <div className="smc-mobile-tools" role="toolbar" aria-label="Course tools">
                        <button
                            type="button"
                            className="smc-mobile-tool-btn"
                            onClick={() => {
                                setMobileNotesOpen(false);
                                setMobileNavOpen((open) => !open);
                            }}
                        >
                            {mobileNavOpen ? <X size={16} /> : <BookOpen size={16} />}
                            <span>{mobileNavOpen ? 'Close' : 'Lessons'}</span>
                        </button>
                        <button
                            type="button"
                            className="smc-mobile-tool-btn"
                            onClick={() => {
                                setMobileNavOpen(false);
                                setMobileNotesOpen((open) => !open);
                            }}
                        >
                            {mobileNotesOpen ? <X size={16} /> : <NotebookPen size={16} />}
                            <span>{mobileNotesOpen ? 'Close' : 'Notes'}</span>
                        </button>
                    </div>
                    <div className="smc-player-title-wrap">
                        <h1>{activeLesson?.title || 'Select a Lesson'}</h1>
                        {instructor?.id > 0 && (
                            <button
                                type="button"
                                className="smc-instructor-link-btn"
                                onClick={() => setShowInstructorModal(true)}
                            >
                                <UserCircle2 size={15} />
                                <span>Instructor Profile</span>
                            </button>
                        )}
                    </div>
                </header>
                <main className="smc-lesson-viewer">
                    <div className="smc-lesson-body">
                        {activeLesson ? (
                            activeLesson.type === 'video' ? (
                                <VideoRenderer lesson={activeLesson} />
                            ) : (
                                <TextRenderer lesson={activeLesson} />
                            )
                        ) : (
                            <div className="p-10 text-center text-gray-500">Select a lesson to start.</div>
                        )}
                    </div>
                </main>
                <footer className="smc-player-footer">
                    <button
                        className="smc-btn-complete"
                        onClick={handleComplete}
                        disabled={completing}
                    >
                        {completing ? 'Saving...' : 'Complete & Next'}
                    </button>
                </footer>
            </div>

            {/* Notes Sidebar */}
            {activeLesson && (
                <NotesSidebar
                    lessonId={activeLesson.id}
                    className={mobileNotesOpen ? 'is-mobile-open' : ''}
                />
            )}
            {/* Completion Modal */}
            {showCompletionModal && (
                <div className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
                    <div className="bg-base-100 max-w-md w-full rounded-2xl p-8 text-center shadow-2xl border border-base-content/10 transform transition-all scale-100">
                        <div className="w-20 h-20 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
                            <span className="text-4xl">🎉</span>
                        </div>
                        <h2 className="text-2xl font-bold mb-2">Course Completed!</h2>
                        <p className="text-base-content/60 mb-8">Congratulations! You have finished {structure.title}.</p>
                        <button
                            onClick={onExit}
                            className="w-full py-3 bg-primary text-white font-bold rounded-lg hover:brightness-110 transition-all shadow-lg shadow-primary/30"
                        >
                            Back to Dashboard
                        </button>
                    </div>
                </div>
            )}

            {instructor?.id > 0 && (
                <button
                    type="button"
                    className="smc-instructor-fab"
                    onClick={() => setShowInstructorModal(true)}
                    aria-label={`Open ${instructor?.name || 'Instructor'} profile`}
                >
                    {instructor?.avatar ? (
                        <img src={instructor.avatar} alt={instructor?.name || 'Instructor'} />
                    ) : (
                        <UserCircle2 size={24} />
                    )}
                </button>
            )}

            {showInstructorModal && instructor?.id > 0 && (
                <div className="smc-instructor-modal-overlay" role="dialog" aria-modal="true" aria-label="Instructor profile">
                    <div className="smc-instructor-modal">
                        <button
                            type="button"
                            className="smc-instructor-modal-close"
                            onClick={() => setShowInstructorModal(false)}
                            aria-label="Close instructor profile"
                        >
                            <X size={18} />
                        </button>

                        <div className="smc-instructor-modal-head">
                            {instructor?.avatar ? (
                                <img src={instructor.avatar} alt={instructor?.name || 'Instructor'} className="smc-instructor-avatar" />
                            ) : (
                                <div className="smc-instructor-avatar smc-instructor-avatar-fallback">
                                    <UserCircle2 size={28} />
                                </div>
                            )}
                            <div>
                                <h2>{instructor?.name || 'Instructor'}</h2>
                                {instructor?.intro && <p>{instructor.intro}</p>}
                            </div>
                        </div>

                        {instructor?.bio && (
                            <section>
                                <h3>Bio</h3>
                                <p>{instructor.bio}</p>
                            </section>
                        )}

                        {instructor?.experience && (
                            <section>
                                <h3>Experience</h3>
                                <p>{instructor.experience}</p>
                            </section>
                        )}

                        {Array.isArray(instructor?.skills) && instructor.skills.length > 0 && (
                            <section>
                                <h3>Skills</h3>
                                <div className="smc-instructor-skills">
                                    {instructor.skills.map((skill) => (
                                        <span key={skill}>{skill}</span>
                                    ))}
                                </div>
                            </section>
                        )}

                        {socialEntries.length > 0 && (
                            <section>
                                <h3>Social Links</h3>
                                <div className="smc-instructor-links">
                                    {socialEntries.map(([network, url]) => (
                                        <a key={network} href={url} target="_blank" rel="noreferrer">
                                            <Globe size={14} />
                                            {network}
                                        </a>
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
