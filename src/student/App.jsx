import { useState, useEffect, useRef } from '@wordpress/element';
import { BookOpen, TrendingUp, LogOut, ArrowRight, CheckCircle, Play, RotateCcw, Lock } from 'lucide-react';
import { gsap } from 'gsap';
import CoursePlayer from './CoursePlayer';
import './style.scss';

export default function App() {
    const [view, setView] = useState('dashboard'); // 'dashboard' | 'player'
    const [activeCourseId, setActiveCourseId] = useState(null);
    const [courses, setCourses] = useState([]);
    const [loading, setLoading] = useState(true);
    const headerRef = useRef(null);
    const gridRef = useRef(null);

    useEffect(() => {
        const fetchDashboard = async () => {
            try {
                const response = await fetch(`${wpApiSettings.root}smc/v1/student/dashboard`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                const data = await response.json();
                setCourses(data.courses || []);

                // Animation after data load
                setTimeout(() => {
                    const tl = gsap.timeline({ defaults: { ease: "power4.out", duration: 1 } });
                    tl.from(headerRef.current, { y: -20, opacity: 0, delay: 0.2 })
                        .from(".smc-dashboard-intro", { y: 20, opacity: 0 }, "-=0.6")
                        .from(".smc-course-card", { y: 30, opacity: 0, stagger: 0.1 }, "-=0.4");
                }, 100);
            } catch (err) {
                console.error("Failed to load dashboard", err);
            } finally {
                setLoading(false);
            }
        };
        fetchDashboard();
    }, [view]); // Reload when returning to dashboard to update progress

    const handleOpenCourse = (course) => {
        if (course.is_locked) {
            window.location.href = '/shop'; // Redirect to upgrade
            return;
        }
        setActiveCourseId(course.id);
        setView('player');
    };

    const getActionLabel = (course) => {
        if (course.is_locked) return { text: 'LOCKED • UPGRADE', icon: Lock, isLocked: true };
        switch (course.status) {
            case 'completed': return { text: 'REVIEW MODULE', icon: RotateCcw };
            case 'in_progress': return { text: 'RESUME MODULE', icon: ArrowRight };
            default: return { text: 'BEGIN MODULE', icon: Play };
        }
    };

    if (view === 'player' && activeCourseId) {
        return <CoursePlayer courseId={activeCourseId} onExit={() => setView('dashboard')} />;
    }

    return (
        <div className="smc-student-app">
            {/* Premium Radial Backgrounds */}
            <div className="smc-radial-bg">
                <div className="bg-gradient-teal"></div>
                <div className="bg-gradient-red"></div>
            </div>

            <header className="smc-student-header" ref={headerRef}>
                <div className="smc-header-container">
                    <div className="flex items-center gap-4">
                        {wpApiSettings.siteLogo ? (
                            <img src={wpApiSettings.siteLogo} alt={wpApiSettings.siteName} className="w-10 h-10 rounded object-contain" />
                        ) : (
                            <div className="w-10 h-10 rounded bg-teal-500/10 flex items-center justify-center font-bold text-teal-500 shrink-0">
                                {wpApiSettings.siteName?.charAt(0)}
                            </div>
                        )}
                        <div className="smc-brand">
                            <span className="smc-badge-mini">{wpApiSettings.siteName || 'LEARNING EXPERIENCE'}</span>
                            <h1 className="smc-premium-heading text-xl">Student Portal</h1>
                        </div>
                    </div>

                    <div className="flex items-center gap-6">
                        {wpApiSettings.user && (
                            <div className="flex items-center gap-3 pr-6 border-r border-base-content/10 hidden sm:flex">
                                <div className="text-right">
                                    <div className="text-xs font-bold text-base-content">{wpApiSettings.user.name}</div>
                                    <div className="text-[10px] text-base-content/40 uppercase tracking-widest">Student</div>
                                </div>
                                {wpApiSettings.user.avatar ? (
                                    <img src={wpApiSettings.user.avatar} alt={wpApiSettings.user.name} className="w-8 h-8 rounded-full border border-base-content/10" />
                                ) : (
                                    <div className="w-8 h-8 rounded-full bg-base-content/5 flex items-center justify-center text-xs font-bold text-base-content/40">
                                        {wpApiSettings.user.name?.charAt(0)}
                                    </div>
                                )}
                            </div>
                        )}
                        <a href={window.location.origin + '/my-account/'} className="smc-btn-logout">
                            <LogOut size={16} />
                            <span className="hidden md:inline">BACK TO ACCOUNT</span>
                        </a>
                    </div>
                </div>
            </header>

            <main className="smc-student-content">
                {loading ? (
                    <div className="smc-loading">Curating your workspace...</div>
                ) : (
                    <>
                        <div className="smc-dashboard-intro mb-12">
                            <span className="smc-premium-badge">CONTINUE JOURNEY</span>
                            <h2 className="smc-premium-heading text-4xl mt-4">Active Modules</h2>
                            <p className="text-base-content/60 mt-2 max-w-xl">Deepen your expertise with our boutique African business science modules.</p>
                        </div>

                        <div className="smc-course-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" ref={gridRef}>
                            {courses.length === 0 ? (
                                <div className="col-span-full smc-glass-card p-12 text-center">
                                    <p className="text-smc-text-muted">You are not enrolled in any courses yet.</p>
                                    <a href="/shop" className="smc-btn-primary mt-6 inline-block">Explore Modules</a>
                                </div>
                            ) : (
                                courses.map(course => {
                                    const action = getActionLabel(course);
                                    const ActionIcon = action.icon;
                                    const isLocked = action.isLocked;

                                    return (
                                        <div key={course.id} className={`smc-course-card smc-glass-card group overflow-hidden flex flex-col h-full ${isLocked ? 'grayscale opacity-80' : ''}`}>
                                            <div className="smc-course-thumb relative h-48 overflow-hidden flex-shrink-0">
                                                {course.thumbnail ? (
                                                    <img src={course.thumbnail} alt={course.title} className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                                                ) : (
                                                    <div className="w-full h-full bg-smc-teal/5 flex items-center justify-center">
                                                        <BookOpen size={48} className="text-smc-teal opacity-20" />
                                                    </div>
                                                )}
                                                <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-6">
                                                    <button onClick={() => handleOpenCourse(course)} className="text-white font-bold flex items-center gap-2 tracking-widest text-xs">
                                                        {action.text} <ActionIcon size={14} />
                                                    </button>
                                                </div>
                                                <div className="absolute top-4 right-4">
                                                    {course.status === 'completed' && (
                                                        <span className="bg-green-500 text-white text-[10px] font-black uppercase px-2 py-1 rounded shadow-lg flex items-center gap-1">
                                                            <CheckCircle size={10} /> Completed
                                                        </span>
                                                    )}
                                                    {isLocked && (
                                                        <span className="bg-gray-800 text-white text-[10px] font-black uppercase px-2 py-1 rounded shadow-lg flex items-center gap-1">
                                                            <Lock size={10} /> Locked
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="smc-course-info p-8 flex flex-col flex-grow">
                                                <h3 className="smc-premium-heading text-xl mb-4 h-14 overflow-hidden line-clamp-2">{course.title}</h3>

                                                <div className="smc-progress-container mb-6 mt-auto">
                                                    <div className="flex justify-between text-[10px] font-black tracking-widest uppercase mb-2">
                                                        <span className="opacity-50">Mastery Level</span>
                                                        <span className={course.status === 'completed' ? 'text-green-500' : 'text-teal-500'}>{course.progress}%</span>
                                                    </div>
                                                    <div className="smc-progress-bar h-1.5 bg-base-content/10 rounded-full overflow-hidden">
                                                        <div
                                                            className={`smc-progress-fill h-full transition-all duration-1000 ${course.status === 'completed' ? 'bg-green-500' : 'bg-teal-500'}`}
                                                            style={{ width: `${course.progress}%` }}
                                                        ></div>
                                                    </div>
                                                </div>

                                                <button
                                                    onClick={() => handleOpenCourse(course)}
                                                    className={`w-full py-4 border border-smc-teal/20 rounded-xl text-[11px] font-black tracking-widest uppercase hover:bg-smc-teal hover:text-white transition-all flex items-center justify-center gap-2 ${isLocked ? 'bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-600' : ''}`}
                                                >
                                                    {action.text} <ActionIcon size={14} />
                                                </button>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </>
                )}
            </main>
        </div>
    );
}
