import { useState, useEffect, useRef } from '@wordpress/element';
import { Users, BookOpen, CheckCircle, TrendingUp, Layout } from 'lucide-react';
import { gsap } from 'gsap';

export default function Dashboard() {
    const [stats, setStats] = useState(null);
    const statsRef = useRef(null);

    useEffect(() => {
        fetch(`${wpApiSettings.root}smc/v1/instructor/stats`, {
            headers: { 'X-WP-Nonce': wpApiSettings.nonce }
        })
            .then(res => res.json())
            .then(data => {
                setStats(data);
                if (statsRef.current) {
                    gsap.fromTo(statsRef.current.querySelectorAll('.smc-stat-card'),
                        { y: 30, opacity: 0 },
                        { y: 0, opacity: 1, duration: 0.8, stagger: 0.15, ease: "power3.out" }
                    );
                }
            });
    }, []);

    if (!stats) return <div className="smc-loading">Loading boutique stats...</div>;

    const statItems = [
        { label: 'TOTAL STUDENTS', value: stats.total_students, icon: Users, color: '--smc-teal' },
        { label: 'ACTIVE COURSES', value: stats.active_courses, icon: BookOpen, color: '--smc-gold' },
        { label: 'COMPLETIONS TODAY', value: stats.completions_today, icon: CheckCircle, color: '--smc-red' }
    ];

    return (
        <div className="smc-instructor-dashboard max-w-7xl mx-auto">
            <div className="smc-dashboard-intro mb-12">
                <span className="inline-block px-3 py-1 rounded-full bg-teal-500/10 text-teal-600 dark:text-teal-400 text-xs font-bold tracking-widest mb-4 border border-teal-500/20">OVERVIEW</span>
                <h2 className="text-5xl font-black text-base-content tracking-tight mb-4">
                    Performance <span className="bg-gradient-to-r from-teal-500 to-blue-600 dark:from-teal-400 dark:to-blue-500 bg-clip-text text-transparent">Analytics</span>
                </h2>
                <p className="text-base-content/60 text-lg max-w-2xl leading-relaxed">
                    Track your students' progress and course engagement through our refined science-backed dashboard.
                </p>
            </div>

            <div className="smc-stats-grid grid grid-cols-1 md:grid-cols-3 gap-8 mb-16" ref={statsRef}>
                {statItems.map((item, index) => {
                    const Icon = item.icon;
                    return (
                        <div key={index} className="smc-stat-card smc-glass-card group hover:bg-base-content/5 transition-all duration-500 p-8 relative overflow-hidden bg-base-200/50 backdrop-blur-xl border border-base-content/5">
                            <div className="absolute -right-6 -top-6 text-base-content/5 transform rotate-12 group-hover:scale-110 transition-transform duration-700">
                                <Icon size={180} />
                            </div>

                            <div className="relative z-10">
                                <div className="text-base-content/50 text-sm font-bold tracking-widest mb-2 flex items-center gap-2">
                                    <Icon size={16} className="text-teal-500" />
                                    {item.label}
                                </div>
                                <div className="flex items-end gap-3">
                                    <span className="text-5xl font-bold text-base-content tracking-tighter">{item.value}</span>
                                    <div className="flex items-center gap-1 text-green-500 dark:text-green-400 text-sm font-medium mb-1.5 bg-green-500/10 px-2 py-0.5 rounded">
                                        <TrendingUp size={12} />
                                        <span>+12%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="smc-dashboard-actions">
                <div className="flex items-center gap-4 mb-8">
                    <h3 className="text-2xl font-bold text-base-content">Quick Actions</h3>
                    <div className="h-px flex-1 bg-gradient-to-r from-base-content/10 to-transparent"></div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <a href="/course-builder/" className="group relative overflow-hidden rounded-3xl bg-base-200 border border-base-content/5 hover:border-teal-500/30 transition-all duration-500 shadow-md">
                        {/* Hover Gradient Background */}
                        <div className="absolute inset-0 bg-gradient-to-br from-teal-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                        <div className="relative p-10 z-10">
                            <div className="w-14 h-14 rounded-2xl bg-teal-500/10 flex items-center justify-center mb-6 text-teal-600 dark:text-teal-400 group-hover:scale-110 group-hover:bg-teal-500/20 transition-all duration-500">
                                <BookOpen size={28} />
                            </div>

                            <h4 className="text-2xl font-bold text-base-content mb-3">Course Builder</h4>
                            <p className="text-base-content/60 mb-8 max-w-sm leading-relaxed">
                                Create and manage your curriculum, quizzes, and lessons in the new premium editor.
                            </p>

                            <div className="flex items-center text-teal-600 dark:text-teal-400 font-bold tracking-wide text-sm group-hover:translate-x-2 transition-transform duration-300">
                                LAUNCH BUILDER <Layout size={16} className="ml-2" />
                            </div>
                        </div>

                        {/* Decorative Blur */}
                        <div className="absolute -bottom-20 -right-20 w-64 h-64 bg-teal-500/10 rounded-full blur-[80px] group-hover:bg-teal-500/20 transition-colors duration-500"></div>
                    </a>
                </div>
            </div>
        </div>
    );
}
