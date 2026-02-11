import { useState, useEffect } from '@wordpress/element';
import { LayoutDashboard, Layers, ArrowLeft, Moon, Sun } from 'lucide-react';
import CourseBuilder from './CourseBuilder';
import './style.scss'; // Ensure we have styles

export default function CourseBuilderApp() {
    const [currentView, setCurrentView] = useState('dashboard'); // dashboard, editor, settings
    const [darkMode, setDarkMode] = useState(true);

    useEffect(() => {
        document.body.classList.add('smc-admin-clean');
        return () => document.body.classList.remove('smc-admin-clean');
    }, []);

    const toggleTheme = () => {
        setDarkMode(!darkMode);
        // Implementation depend on how theme is handled globally, for now toggle class on wrapper
    };

    return (
        <div className={`smc-course-builder-app min-h-screen flex bg-base-100 text-base-content ${darkMode ? 'dark-theme' : 'light-theme'}`}>

            {/* Sidebar Navigation */}
            <aside className="smc-sidebar w-64 border-r border-base-content/10 flex flex-col justify-between p-6 bg-base-100">
                <div>
                    {wpApiSettings.siteName && (
                        <div className="smc-brand mb-10 flex items-center gap-3">
                            {wpApiSettings.siteLogo ? (
                                <img src={wpApiSettings.siteLogo} alt={wpApiSettings.siteName} className="w-8 h-8 rounded object-contain" />
                            ) : (
                                <div className="w-8 h-8 rounded bg-teal-500/10 flex items-center justify-center font-bold text-teal-500 shrink-0">
                                    {wpApiSettings.siteName.charAt(0)}
                                </div>
                            )}
                            <span className="font-bold text-lg tracking-wider text-base-content truncate">{wpApiSettings.siteName}</span>
                        </div>
                    )}

                    <nav className="space-y-2">
                        <button
                            className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-all ${currentView === 'dashboard' ? 'bg-base-content/10 text-base-content' : 'text-base-content/50 hover:text-base-content hover:bg-base-content/5'}`}
                            onClick={() => setCurrentView('dashboard')}
                        >
                            <LayoutDashboard size={18} />
                            <span>Dashboard</span>
                        </button>
                        <button
                            className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-all ${currentView === 'editor' ? 'bg-base-content/10 text-base-content' : 'text-base-content/50 hover:text-base-content hover:bg-base-content/5'}`}
                            onClick={() => setCurrentView('editor')}
                        >
                            <Layers size={18} />
                            <span>My Courses</span>
                        </button>
                    </nav>
                </div>

                <div className="smc-user-actions border-t border-base-content/10 pt-6">
                    <a href="/wp-admin" className="flex items-center gap-3 text-base-content/50 hover:text-base-content transition-colors">
                        <ArrowLeft size={18} />
                        <span>Back to WP</span>
                    </a>
                </div>
            </aside>

            {/* Main Content Area */}
            <main className="flex-1 flex flex-col relative overflow-hidden">
                {/* Top Header */}
                <header className="h-16 border-b border-base-content/10 flex items-center justify-between px-8 bg-base-100/80 backdrop-blur-md z-10">
                    <div className="text-base-content/50 text-sm">
                        Course Builder <span className="mx-2">/</span> <span className="text-base-content capitalize">{currentView}</span>
                    </div>

                    <div className="flex items-center gap-4">
                        <button className="p-2 rounded-full hover:bg-base-content/5 text-base-content/50 hover:text-base-content" onClick={toggleTheme}>
                            {darkMode ? <Sun size={18} /> : <Moon size={18} />}
                        </button>

                        {wpApiSettings.user && (
                            <div className="flex items-center gap-3 pl-4 border-l border-base-content/10">
                                <div className="text-right hidden sm:block">
                                    <div className="text-xs font-bold text-base-content">{wpApiSettings.user.name}</div>
                                    <div className="text-[10px] text-base-content/40 uppercase tracking-widest">Instructor</div>
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
                    </div>
                </header>

                {/* Scrollable Canvas */}
                <div className="flex-1 overflow-y-auto p-8 relative">
                    {/* Background Gradients */}
                    <div className="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none -z-10">
                        <div className="absolute top-[10%] left-[20%] w-[500px] h-[500px] bg-teal-500/10 rounded-full blur-[120px]"></div>
                        <div className="absolute bottom-[20%] right-[10%] w-[400px] h-[400px] bg-purple-500/10 rounded-full blur-[100px]"></div>
                    </div>

                    {/* View Router */}
                    {currentView === 'dashboard' && (
                        <div className="max-w-5xl mx-auto">
                            <h1 className="text-4xl font-bold mb-2 bg-gradient-to-r from-base-content to-base-content/60 bg-clip-text text-transparent">Welcome back, Instructor</h1>
                            <p className="text-base-content/60 mb-10">Manage your curriculum and courses from here.</p>
                            <CourseBuilder />
                        </div>
                    )}

                    {currentView === 'editor' && (
                        <div className="max-w-6xl mx-auto">
                            <CourseBuilder />
                        </div>
                    )}

                </div>
            </main>
        </div>
    );
}
