import { useState, useEffect, useRef } from '@wordpress/element';
import { Users, BookOpen, CheckCircle, TrendingUp } from 'lucide-react';
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
        <div className="smc-instructor-dashboard">
            <div className="smc-dashboard-intro mb-10">
                <span className="smc-premium-badge">OVERVIEW</span>
                <h2 className="smc-premium-heading text-4xl mt-4">Performance Analytics</h2>
                <p className="text-smc-text-muted mt-2 max-w-xl">Track your students' progress and course engagement through our refined science-backed dashboard.</p>
            </div>

            <div className="smc-stats-grid grid grid-cols-1 md:grid-cols-3 gap-8" ref={statsRef}>
                {statItems.map((item, index) => {
                    const Icon = item.icon;
                    return (
                        <div key={index} className="smc-stat-card smc-glass-card">
                            <div className="smc-stat-icon-bg">
                                <Icon size={140} />
                            </div>
                            <div className="smc-stat-top">
                                <div className="smc-stat-label">{item.label}</div>
                            </div>
                            <div className="smc-stat-value-container">
                                <span className="smc-stat-value">{item.value}</span>
                                <div className="smc-stat-trend">
                                    <TrendingUp size={16} />
                                    <span>+12%</span>
                                </div>
                            </div>
                        </div>

                    );
                })}
            </div>
        </div>
    );
}
