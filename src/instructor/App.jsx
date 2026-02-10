import { useState, useEffect, useRef } from '@wordpress/element';
import { LayoutDashboard, Users, BookOpen, LogOut } from 'lucide-react';
import Dashboard from './Dashboard';
import StudentManager from './StudentManager';
import CourseBuilder from './CourseBuilder';
import { gsap } from 'gsap';
import './style.scss';

export default function App() {
    const [currentTab, setCurrentTab] = useState('dashboard');
    const appRef = useRef(null);
    const headerRef = useRef(null);

    const tabs = [
        { id: 'dashboard', label: 'DASHBOARD', icon: LayoutDashboard },
        { id: 'students', label: 'STUDENTS', icon: Users },
        { id: 'builder', label: 'BUILDER', icon: BookOpen }
    ];

    useEffect(() => {
        const tl = gsap.timeline({ defaults: { ease: "power4.out", duration: 1.2 } });
        tl.from(headerRef.current, { y: -30, opacity: 0, delay: 0.2 })
            .from(".smc-instructor-content", { y: 30, opacity: 0 }, "-=0.8");
    }, []);

    return (
        <div className="smc-instructor-app" ref={appRef}>
            {/* Premium Radial Backgrounds */}
            <div className="smc-radial-bg">
                <div className="bg-gradient-teal"></div>
                <div className="bg-gradient-red"></div>
            </div>

            <header className="smc-instructor-header" ref={headerRef}>
                <div className="smc-header-container">
                    <div className="smc-brand">
                        <span className="smc-badge-mini">BOUTIQUE LMS</span>
                        <h1 className="smc-premium-heading text-2xl">Instructor Hub</h1>
                    </div>

                    <nav className="smc-instructor-nav">
                        {tabs.map(tab => {
                            const Icon = tab.icon;
                            return (
                                <button
                                    key={tab.id}
                                    className={`smc-nav-item ${currentTab === tab.id ? 'active' : ''}`}
                                    onClick={() => setCurrentTab(tab.id)}
                                >
                                    <Icon size={16} />
                                    <span>{tab.label}</span>
                                </button>
                            );
                        })}
                    </nav>

                    <div className="smc-header-actions">
                        <a href={window.location.origin + '/my-account/'} className="smc-btn-logout">
                            <LogOut size={16} />
                            <span>EXIT</span>
                        </a>
                    </div>
                </div>
            </header>

            <main className="smc-instructor-content">
                {currentTab === 'dashboard' && <Dashboard />}
                {currentTab === 'students' && <StudentManager />}
                {currentTab === 'builder' && <CourseBuilder />}
            </main>
        </div>
    );
}
