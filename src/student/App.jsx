import { useState, useEffect, useRef } from '@wordpress/element';
import { BookOpen, TrendingUp, LogOut, ArrowRight } from 'lucide-react';
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
    }, []);

    const handleOpenCourse = (id) => {
        setActiveCourseId(id);
        setView('player');
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
                    <div className="smc-brand">
                        <span className="smc-badge-mini">LEARNING EXPERIENCE</span>
                        <h1 className="smc-premium-heading text-2xl">My Portal</h1>
                    </div>

                    <div className="smc-header-actions">
                        <a href={window.location.origin + '/my-account/'} className="smc-btn-logout">
                            <LogOut size={16} />
                            <span>BACK TO ACCOUNT</span>
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
                            <p className="text-smc-text-muted mt-2 max-w-xl">Deepen your expertise with our boutique African business science modules.</p>
                        </div>

                        <div className="smc-course-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" ref={gridRef}>
                            {courses.length === 0 ? (
                                <div className="col-span-full smc-glass-card p-12 text-center">
                                    <p className="text-smc-text-muted">You are not enrolled in any courses yet.</p>
                                    <a href="/shop" className="smc-btn-primary mt-6 inline-block">Explore Modules</a>
                                </div>
                            ) : (
                                courses.map(course => (
                                    <div key={course.id} className="smc-course-card smc-glass-card group overflow-hidden">
                                        <div className="smc-course-thumb relative h-48 overflow-hidden">
                                            {course.thumbnail ? (
                                                <img src={course.thumbnail} alt={course.title} className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                                            ) : (
                                                <div className="w-full h-full bg-smc-teal/5 flex items-center justify-center">
                                                    <BookOpen size={48} className="text-smc-teal opacity-20" />
                                                </div>
                                            )}
                                            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-6">
                                                <button onClick={() => handleOpenCourse(course.id)} className="text-white font-bold flex items-center gap-2">
                                                    RESUME MODULE <ArrowRight size={16} />
                                                </button>
                                            </div>
                                        </div>
                                        <div className="smc-course-info p-8">
                                            <h3 className="smc-premium-heading text-xl mb-4 h-14 overflow-hidden line-clamp-2">{course.title}</h3>

                                            <div className="smc-progress-container mb-6">
                                                <div className="flex justify-between text-[10px] font-black tracking-widest uppercase mb-2">
                                                    <span className="opacity-50">Mastery Level</span>
                                                    <span className="text-smc-teal">{course.progress}%</span>
                                                </div>
                                                <div className="smc-progress-bar h-1.5 bg-black/5 rounded-full overflow-hidden">
                                                    <div
                                                        className="smc-progress-fill h-full bg-smc-teal transition-all duration-1000"
                                                        style={{ width: `${course.progress}%` }}
                                                    ></div>
                                                </div>
                                            </div>

                                            <button
                                                onClick={() => handleOpenCourse(course.id)}
                                                className="w-full py-4 border border-smc-teal/20 rounded-xl text-[11px] font-black tracking-widest uppercase hover:bg-smc-teal hover:text-white transition-all"
                                            >
                                                Continue Learning
                                            </button>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </>
                )}
            </main>
        </div>
    );
}
