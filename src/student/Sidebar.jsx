import { ArrowLeft, CheckCircle, Circle } from 'lucide-react';

export default function Sidebar({ title, sections, activeLessonId, onSelectLesson, onExit }) {
    return (
        <aside className="smc-player-sidebar">
            <div className="smc-sidebar-header">
                <button onClick={onExit} className="smc-back-btn">
                    <ArrowLeft size={20} /> Back
                </button>
                <h2 className="smc-course-title">{title}</h2>
            </div>

            <div className="smc-course-curriculum">
                {sections.map((section, sIndex) => (
                    <div key={sIndex} className="smc-section">
                        <h3 className="smc-section-title">{section.title}</h3>
                        <ul className="smc-lesson-list">
                            {section.lessons.map(lesson => (
                                <li
                                    key={lesson.id}
                                    className={`smc-lesson-item ${lesson.id === activeLessonId ? 'active' : ''}`}
                                    onClick={() => onSelectLesson(lesson.id)}
                                >
                                    <span className="smc-lesson-status">
                                        {lesson.status === 'completed' ? (
                                            <CheckCircle size={16} className="text-green-500" />
                                        ) : (
                                            <Circle size={16} className="text-gray-600" />
                                        )}
                                    </span>
                                    <div className="flex-1 min-w-0">
                                        <span className="smc-lesson-title block truncate">{lesson.title}</span>
                                        <span className="smc-lesson-meta">
                                            <span>{lesson.type === 'video' ? 'Video' : 'Text'}</span>
                                            {lesson.duration > 0 && <span>• {lesson.duration}m</span>}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </aside>
    );
}
