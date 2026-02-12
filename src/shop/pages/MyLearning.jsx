import { ArrowRight, BookOpenText, Crown, Sparkles, TrendingUp } from 'lucide-react';

export default function MyLearning({ userAccess }) {
    if (!userAccess) {
        return (
            <div className="learning-restricted">
                <h2>Restricted Workspace</h2>
                <p>Please log in to your SMC account to access your business science toolkit.</p>
            </div>
        );
    }

    const plan = (userAccess.plan || 'free').toLowerCase();
    const planLabel = `${plan.charAt(0).toUpperCase()}${plan.slice(1)}`;
    const enrollments = Array.isArray(userAccess.enrollments) ? userAccess.enrollments : [];
    const totalEnrolled = enrollments.length;
    const completedCourses = enrollments.filter((course) => Number(course.progress || 0) >= 100).length;
    const inProgressCourses = enrollments.filter((course) => {
        const progress = Number(course.progress || 0);
        return progress > 0 && progress < 100;
    }).length;
    const averageProgress = totalEnrolled > 0
        ? Math.round(enrollments.reduce((sum, course) => sum + Number(course.progress || 0), 0) / totalEnrolled)
        : 0;

    return (
        <div className="learning-dashboard fade-in">
            <section className={`learning-hero learning-hero--${plan}`}>
                <div className="learning-hero__glow" aria-hidden="true"></div>
                <div className="learning-hero__content">
                    <span className="learning-hero__eyebrow">Current Membership</span>
                    <h2>{planLabel} Access</h2>
                    <p>You have full access to all materials associated with your level.</p>
                    <div className="learning-hero__meta">
                        <span className="learning-chip"><Crown size={14} /> Active Plan</span>
                        <span className="learning-chip"><Sparkles size={14} /> Personalized Path</span>
                    </div>
                </div>
                <div className="learning-hero__stats">
                    <div className="learning-stat">
                        <BookOpenText size={18} />
                        <div>
                            <strong>Tracks</strong>
                            <span>
                                {totalEnrolled > 0
                                    ? `${averageProgress}% average progress`
                                    : 'No active tracks'}
                            </span>
                        </div>
                    </div>
                    <div className="learning-stat">
                        <TrendingUp size={18} />
                        <div>
                            <strong>Growth Map</strong>
                            <span>{`Enrolled ${totalEnrolled} • Completed ${completedCourses}`}</span>
                        </div>
                    </div>
                </div>
            </section>

            <div className="learning-panels">
                <div className="learning-panel">
                    <h3>My Training Tracks</h3>
                    <p>Your personalized training modules will appear here as you complete assessments.</p>

                    {totalEnrolled > 0 ? (
                        <div className="learning-grid">
                            {enrollments.map(course => (
                                <div key={course.id} className="learning-card">
                                    <div className="learning-card__body">
                                        <h4>{course.title}</h4>
                                        <div className="learning-progress">
                                            <div className="learning-progress__bar" style={{ width: `${course.progress}%` }}></div>
                                        </div>
                                        <div className="learning-card__meta">
                                            <span>{Math.round(course.progress)}% Complete</span>
                                            <a href={course.link} className="learning-action-btn">
                                                <span>Continue</span>
                                                <ArrowRight size={15} aria-hidden="true" />
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="learning-empty">
                            <p>You are not enrolled in any courses yet.</p>
                            <a href="#" onClick={(e) => { e.preventDefault(); window.history.back(); }} className="smc-link">Browse Shop</a>
                        </div>
                    )}
                </div>

                <div className="learning-panel">
                    <h3>Growth Overview</h3>
                    <p>A quick snapshot of your learning activity and completion momentum.</p>

                    <div className="learning-growth">
                        <div className="learning-growth__item">
                            <span>Enrolled Courses</span>
                            <strong>{totalEnrolled}</strong>
                        </div>
                        <div className="learning-growth__item">
                            <span>Completed Courses</span>
                            <strong>{completedCourses}</strong>
                        </div>
                        <div className="learning-growth__item">
                            <span>In Progress</span>
                            <strong>{inProgressCourses}</strong>
                        </div>
                        <div className="learning-growth__item">
                            <span>Average Progress</span>
                            <strong>{`${averageProgress}%`}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
