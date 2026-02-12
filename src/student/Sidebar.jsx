import { ArrowLeft, CheckCircle, Circle } from 'lucide-react';

export default function Sidebar({ title, sections, activeLessonId, onSelectLesson, onExit, progress = 0, className = '' }) {
    return (
        <aside className={`smc-player-sidebar flex flex-col h-full bg-base-100 border-r border-base-content/10 ${className}`.trim()}>
            <div className="smc-sidebar-header p-4 border-b border-base-content/10">
                <button type="button" onClick={onExit} className="smc-back-btn flex items-center gap-2 text-base-content/60 hover:text-base-content mb-3 transition-colors">
                    <ArrowLeft size={18} /> Back to Dashboard
                </button>
                <h2 className="smc-course-title font-bold text-lg leading-tight">{title}</h2>
            </div>

            <div className="smc-course-curriculum flex-1 overflow-y-auto p-4 space-y-6">
                {sections.map((section, sIndex) => (
                    <div key={sIndex} className="smc-section">
                        <h3 className="smc-section-title text-xs font-bold uppercase tracking-wider text-base-content/40 mb-3 ml-2">{section.title}</h3>
                        <ul className="smc-lesson-list space-y-1">
                            {section.lessons.map(lesson => (
                                <li
                                    key={lesson.id}
                                    className={`smc-lesson-item p-2 rounded-lg cursor-pointer flex items-start gap-3 transition-all ${lesson.id === activeLessonId ? 'bg-primary/10 text-primary' : 'hover:bg-base-200'}`}
                                    onClick={() => onSelectLesson(lesson.id)}
                                >
                                    <span className="smc-lesson-status mt-1">
                                        {lesson.status === 'completed' ? (
                                            <CheckCircle size={16} className="text-emerald-500" />
                                        ) : (
                                            <Circle size={16} className={`text-base-content/20 ${lesson.id === activeLessonId ? 'text-primary' : ''}`} />
                                        )}
                                    </span>
                                    <div className="flex-1 min-w-0">
                                        <span className={`smc-lesson-title block truncate text-sm font-medium ${lesson.status === 'completed' ? 'text-base-content/60 line-through' : ''} ${lesson.id === activeLessonId ? 'text-primary' : ''}`}>{lesson.title}</span>
                                        <div className="smc-lesson-meta flex items-center gap-2 text-xs text-base-content/40 mt-0.5">
                                            <span>{lesson.type === 'video' ? 'Video' : 'Text'}</span>
                                            {lesson.duration > 0 && <span>• {lesson.duration}m</span>}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>

            <div className="smc-sidebar-footer p-4 border-t border-base-content/10 bg-base-200">
                <div className="flex justify-between text-xs font-bold mb-2 text-base-content/70">
                    <span>Course Progress</span>
                    <span>{Math.round(progress)}%</span>
                </div>
                <div className="w-full bg-base-content/10 rounded-full h-2 overflow-hidden">
                    <div
                        className="bg-emerald-500 h-full rounded-full transition-all duration-500 ease-out"
                        style={{ width: `${Math.min(100, Math.max(0, progress))}%` }}
                    ></div>
                </div>
            </div>
        </aside>
    );
}
