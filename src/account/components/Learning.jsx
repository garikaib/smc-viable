import { ExternalLink, Award } from 'lucide-react';

const Learning = ({ data }) => {
    const enrollments = data.enrollments || [];
    const recommendations = data.recommendations || [];

    return (
        <div className="learning-pane">
            {enrollments.length > 0 ? (
                <>
                    <h3 className="section-title">Enrolled Courses</h3>
                    <div className="enrollments-grid">
                        {enrollments.map(course => (
                            <div key={course.id} className="enroll-card">
                                <div
                                    className="enroll-thumb"
                                    style={{ backgroundImage: `url(${course.thumbnail})` }}
                                ></div>
                                <div className="enroll-body">
                                    <a href={course.link} className="enroll-title">{course.title}</a>
                                    <div className="progress-bar">
                                        <div className="progress-fill" style={{ width: `${course.progress}%` }}></div>
                                    </div>
                                    <div className="enroll-meta">
                                        <span>{Math.round(course.progress)}% Complete</span>
                                        <span>{course.status}</span>
                                    </div>
                                    <div style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
                                        <a href={course.link} className="smc-btn smc-btn-primary smc-btn-small" style={{ flex: 1, textAlign: 'center' }}>
                                            {course.action_label} &rarr;
                                        </a>
                                        {course.certificate_url && (
                                            <a href={course.certificate_url} className="smc-btn smc-btn-outline smc-btn-small" title="Download Certificate">
                                                <Award size={16} />
                                            </a>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            ) : (
                <div className="dash-card center" style={{ padding: '60px' }}>
                    <h3 style={{ color: 'var(--dash-text)' }}>No Active Courses Found</h3>
                    <p className="text-muted">You haven't enrolled in any courses yet. Start your journey with our top recommendations.</p>
                </div>
            )}

            {recommendations.length > 0 && (
                <div style={{ marginTop: '50px' }}>
                    <div className="recommend-header">
                        <h3 className="section-title">Recommended for You</h3>
                        <p style={{ fontSize: '12px', color: 'var(--dash-text-muted)' }}>Based on your {data.plan?.label}</p>
                    </div>
                    <div className="enrollments-grid">
                        {recommendations.map(c => (
                            <div key={c.id} className="enroll-card">
                                <div
                                    className="enroll-thumb"
                                    style={{ backgroundImage: `url(${c.thumbnail})` }}
                                ></div>
                                <div className="enroll-body">
                                    <span style={{ fontSize: '10px', color: '#0E7673', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '1px', marginBottom: '5px', display: 'block' }}>
                                        {c.type === 'included' ? 'Included in Plan' : 'Recommended'}
                                    </span>
                                    <a href={c.link} className="enroll-title" style={{ marginBottom: '15px' }}>{c.title}</a>
                                    <a href={c.link} className="smc-btn smc-btn-primary smc-btn-small" style={{ width: '100%', textAlign: 'center' }}>
                                        Explore Course
                                    </a>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

export default Learning;
