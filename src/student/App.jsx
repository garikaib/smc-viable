import { useState, useEffect, useRef } from '@wordpress/element';
import { BookOpen, TrendingUp, LogOut, ArrowRight, CheckCircle, Play, RotateCcw, Lock } from 'lucide-react';
import { gsap } from 'gsap';
import CoursePlayer from './CoursePlayer';
import AnimatedLoader from '../components/AnimatedLoader';
import './style.scss';

export default function App() {
    const [view, setView] = useState('dashboard'); // 'dashboard' | 'player'
    const [activeCourseId, setActiveCourseId] = useState(null);
    const [activeCourseSlug, setActiveCourseSlug] = useState('');
    const [courses, setCourses] = useState([]);
    const [loading, setLoading] = useState(true);
    const headerRef = useRef(null);
    const gridRef = useRef(null);
    const initialCourseSlug = String(wpApiSettings?.currentCourseSlug || '').trim();

    useEffect(() => {
        if (!initialCourseSlug) {
            return;
        }

        setActiveCourseId(null);
        setActiveCourseSlug(initialCourseSlug);
        setView('player');
    }, [initialCourseSlug]);

    useEffect(() => {
        const isAuthIssue = (status, payload) => {
            const code = String(payload?.code || '');
            return status === 401 || status === 403 || code === 'rest_cookie_invalid_nonce' || code === 'rest_not_logged_in';
        };

        const refreshRestNonce = async () => {
            if (!wpApiSettings?.ajaxUrl) return null;

            const response = await fetch(`${wpApiSettings.ajaxUrl}?action=smc_refresh_rest_nonce`, {
                credentials: 'same-origin',
            });
            const payload = await response.json();
            const nonce = payload?.success ? payload?.data?.nonce : null;

            if (!nonce) return null;
            wpApiSettings.nonce = nonce;
            return nonce;
        };

        const fetchJsonWithNonceRetry = async (path) => {
            const request = async () => {
                const response = await fetch(`${wpApiSettings.root}${path}`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce },
                    credentials: 'same-origin',
                });

                let payload = null;
                try {
                    payload = await response.json();
                } catch (err) {
                    payload = null;
                }

                return { response, payload };
            };

            let result = await request();
            if (result.response.ok) {
                return result.payload;
            }

            if (isAuthIssue(result.response.status, result.payload)) {
                const nonce = await refreshRestNonce();
                if (nonce) {
                    result = await request();
                    if (result.response.ok) {
                        return result.payload;
                    }
                }
            }

            throw new Error(result?.payload?.message || `Request failed with status ${result.response.status}`);
        };

        const fetchDashboard = async () => {
            try {
                const data = await fetchJsonWithNonceRetry('smc/v1/student/dashboard');
                const studentCourses = Array.isArray(data?.courses) ? data.courses : [];

                if (studentCourses.length > 0) {
                    setCourses(studentCourses);
                } else {
                    // Fallback to account API enrollment payload to avoid empty portal when dashboard data lags.
                    const accountData = await fetchJsonWithNonceRetry('smc/v1/account/profile');
                    const enrollments = Array.isArray(accountData?.enrollments) ? accountData.enrollments : [];

                    const mapped = enrollments.map((course) => {
                        const progress = Number.isFinite(Number(course?.progress)) ? Number(course.progress) : 0;
                        const rawStatus = String(course?.status || '').toLowerCase();
                        let status = 'not_started';
                        if (progress > 0) status = 'in_progress';
                        if (rawStatus.includes('completed') || progress >= 100) status = 'completed';

                        return {
                            id: Number(course?.id) || 0,
                            title: course?.title || 'Course',
                            slug: course?.slug || '',
                            thumbnail: course?.thumbnail || null,
                            progress,
                            status,
                            access_source: 'Enrolled',
                            lessons_completed: Number(course?.lessons_completed || 0),
                            total_lessons: Number(course?.total_lessons || 0),
                            is_locked: false,
                        };
                    }).filter((c) => c.id > 0);

                    setCourses(mapped);
                }

                // Animation after data load
                setTimeout(() => {
                    const tl = gsap.timeline({ defaults: { ease: "power4.out", duration: 1 } });
                    // Use fromTo for header to handle re-renders gracefully without needing initial CSS classes on sticky header
                    tl.fromTo(headerRef.current, { y: -20, opacity: 0 }, { y: 0, opacity: 1, delay: 0.2 })
                        .to(".smc-dashboard-intro", { y: 0, opacity: 1 }, "-=0.6")
                        .to(".smc-course-card", { y: 0, opacity: 1, stagger: 0.1 }, "-=0.4");
                }, 50);
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
        setActiveCourseSlug(course?.slug || '');
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

    if (view === 'player' && (activeCourseId || activeCourseSlug)) {
        return <CoursePlayer courseId={activeCourseId} courseSlug={activeCourseSlug} onExit={() => setView('dashboard')} />;
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
                    <AnimatedLoader message="Curating your workspace..." />
                ) : (
                    <>
                        <div className="smc-dashboard-intro mb-12 opacity-0 translate-y-4">
                            <span className="smc-premium-badge">CONTINUE JOURNEY</span>
                            <h2 className="smc-premium-heading text-4xl mt-4">Active Modules</h2>
                            <p className="text-base-content/60 mt-2 max-w-xl">Deepen your expertise with our boutique African business science modules.</p>
                        </div>

                        <div className="smc-course-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" ref={gridRef}>
                            {courses.length === 0 ? (
                                <div className="col-span-full smc-glass-card p-20 text-center flex flex-col items-center justify-center border border-white/5 relative overflow-hidden group">
                                    <div className="absolute inset-0 bg-gradient-to-br from-teal-500/5 to-transparent opacity-50"></div>

                                    <div className="relative mb-8 transform group-hover:scale-110 transition-transform duration-700">
                                        <div className="w-24 h-24 rounded-3xl bg-teal-500/10 flex items-center justify-center text-teal-500 shadow-2xl border border-teal-500/20">
                                            <TrendingUp size={48} />
                                        </div>
                                        <div className="absolute -bottom-2 -right-2 w-10 h-10 rounded-2xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-500 backdrop-blur-md">
                                            <ArrowRight size={20} />
                                        </div>
                                    </div>

                                    <h3 className="smc-premium-heading text-3xl mb-4 relative">Begin Your Evolution</h3>
                                    <p className="text-base-content/50 max-w-md mx-auto mb-10 leading-relaxed relative">
                                        Your learning hub is ready for expansion. Discover boutique African business science modules tailored to your journey in our shop.
                                    </p>

                                    <div className="flex flex-col sm:flex-row gap-4 relative">
                                        <a href="/shop" className="smc-btn-primary px-10 py-5 flex items-center gap-3 shadow-xl shadow-teal-500/20">
                                            Explore Shop <ArrowRight size={18} />
                                        </a>
                                    </div>

                                    <div className="mt-12 text-[10px] font-black uppercase tracking-[0.3em] text-base-content/20 relative">
                                        Premium Learning Experience
                                    </div>
                                </div>
                            ) : (
                                courses.map(course => {
                                    const action = getActionLabel(course);
                                    const ActionIcon = action.icon;
                                    const isLocked = action.isLocked;

                                    return (
                                        <div key={course.id} className={`smc-course-card smc-glass-card group overflow-hidden flex flex-col h-full opacity-0 translate-y-8 ${isLocked ? 'grayscale opacity-80' : ''}`}>
                                            <div className="smc-course-thumb relative h-48 overflow-hidden flex-shrink-0">
                                                {course.thumbnail ? (
                                                    <img src={course.thumbnail} alt={course.title} className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                                                ) : (
                                                    <div className="w-full h-full bg-teal-500/5 flex items-center justify-center">
                                                        <BookOpen size={48} className="text-teal-500 opacity-20" />
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
                                                    className={`w-full py-4 border border-teal-500/20 rounded-xl text-[11px] font-black tracking-widest uppercase hover:bg-teal-500 hover:text-white transition-all flex items-center justify-center gap-2 ${isLocked ? 'bg-base-200 text-base-content/40 border-transparent hover:bg-base-300 hover:text-base-content/60' : ''}`}
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
