import { useState, useEffect } from '@wordpress/element';
import Sidebar from './Sidebar';
import VideoRenderer from './VideoRenderer';
import TextRenderer from './TextRenderer';
import NotesSidebar from './NotesSidebar';
// styles are now consolidated in style.scss

export default function CoursePlayer({ courseId, onExit }) {
    const [structure, setStructure] = useState(null);
    const [loading, setLoading] = useState(true);
    const [completing, setCompleting] = useState(false);
    const [activeLessonId, setActiveLessonId] = useState(null);

    useEffect(() => {
        const fetchStructure = async () => {
            try {
                const response = await fetch(`${wpApiSettings.root}smc/v1/courses/${courseId}/structure`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                const data = await response.json();
                setStructure(data);

                // Set first lesson as active if none selected
                if (data.sections && data.sections.length > 0) {
                    // Find first available lesson
                    for (const section of data.sections) {
                        if (section.lessons && section.lessons.length > 0) {
                            setActiveLessonId(section.lessons[0].id);
                            break;
                        }
                    }
                }
            } catch (err) {
                console.error("Failed to load course", err);
            } finally {
                setLoading(false);
            }
        };

        if (courseId) {
            fetchStructure();
        }
    }, [courseId]);

    const handleComplete = async () => {
        if (!activeLessonId) return;
        setCompleting(true);

        try {
            await fetch(`${wpApiSettings.root}smc/v1/student/progress/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    course_id: courseId,
                    lesson_id: activeLessonId
                })
            });

            // Update local state to mark as completed
            setStructure(prev => {
                const newSections = prev.sections.map(section => ({
                    ...section,
                    lessons: section.lessons.map(lesson =>
                        lesson.id === activeLessonId ? { ...lesson, status: 'completed' } : lesson
                    )
                }));
                return { ...prev, sections: newSections };
            });

            // Find next lesson
            const allLessons = structure.sections.flatMap(s => s.lessons);
            const currentIndex = allLessons.findIndex(l => l.id === activeLessonId);

            if (currentIndex !== -1 && currentIndex < allLessons.length - 1) {
                setActiveLessonId(allLessons[currentIndex + 1].id);
            } else {
                alert('Course Completed!'); // Replace with better UI later
                onExit();
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

    return (
        <div className="smc-course-player">
            <Sidebar
                title={structure.title}
                sections={structure.sections}
                activeLessonId={activeLessonId}
                onSelectLesson={setActiveLessonId}
                onExit={onExit}
            />
            <div className="smc-player-content">
                <header className="smc-player-header">
                    <h1>{activeLesson?.title || 'Select a Lesson'}</h1>
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
            {activeLesson && <NotesSidebar lessonId={activeLesson.id} />}
        </div>
    );
}
