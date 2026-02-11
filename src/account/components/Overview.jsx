import { ShieldAlert, BookOpen, CheckCircle, ShoppingCart } from 'lucide-react';

const Overview = ({ data }) => {
    const iden = data.business_identity;
    const stats = data.stats;

    return (
        <div className="overview-pane">
            <h3 className="section-title">Dashboard Overview</h3>

            <div className="overview-grid">
                {/* Business Health Card */}
                <div className={`dash-card ${iden?.status === 'assessed' ? 'highlight-card' : ''}`} id="business-health-card">
                    {!iden || iden.status === 'unknown' ? (
                        <div className="intro-card-inner">
                            <div className="intro-card-icon">
                                <ShieldAlert size={64} strokeWidth={1.2} color="#0E7673" />
                            </div>
                            <div className="intro-card-content">
                                <h2 className="intro-card-title">Let's get to know you</h2>
                                <p className="intro-card-text">
                                    You haven't set up your business profile or taken the evaluation quiz yet.
                                    We need this information to personalize your training experience and
                                    provide the most relevant strategic insights.
                                </p>
                                <div className="intro-card-actions">
                                    <a href="/free-assessment/" className="smc-btn smc-btn-primary smc-btn-glow">Take Evaluation</a>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '25px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '30px' }}>
                                <div className="score-circle" style={{
                                    width: '100px', height: '100px', borderRadius: '50%',
                                    background: 'rgba(14,118,115,0.1)', border: '4px solid #0E7673',
                                    display: 'flex', flexDirection: 'column', alignItems: 'center',
                                    justifyContent: 'center', color: 'var(--dash-text)',
                                    boxShadow: '0 0 20px rgba(14,118,115,0.2)'
                                }}>
                                    <span style={{ fontSize: '11px', fontWeight: 800, opacity: 0.6, marginBottom: '-5px' }}>SCORE</span>
                                    <span style={{ fontWeight: 900, fontSize: '32px' }}>{iden.viability_score}</span>
                                </div>
                                <div style={{ flex: 1 }}>
                                    <span style={{ textTransform: 'uppercase', fontSize: '11px', fontWeight: 800, color: '#0E7673', letterSpacing: '1px' }}>Business Lifecycle Stage</span>
                                    <h2 style={{ margin: '5px 0 5px', fontSize: '28px', fontWeight: 900, color: 'var(--dash-text)' }}>{iden.stage}</h2>
                                    <p style={{ fontSize: '14px', color: 'var(--dash-text-muted)', lineHeight: '1.4', margin: 0 }}>
                                        {iden.stage_description}
                                    </p>
                                </div>
                            </div>
                            <div style={{ padding: '15px', background: 'rgba(14,118,115,0.05)', borderRadius: '15px', border: '1px solid rgba(14,118,115,0.1)' }}>
                                <span style={{ fontSize: '11px', fontWeight: 800, color: '#0E7673', textTransform: 'uppercase' }}>Strategic Next Step</span>
                                <p style={{ margin: '5px 0 0', fontSize: '14px', fontStyle: 'italic' }}>
                                    Review your detailed viability roadmap and begin the recommended Efficiency modules to stabilize your operations.
                                </p>
                            </div>
                            <div>
                                <a href={iden.report_link} className="smc-btn smc-btn-outline smc-btn-small">View Full Roadmap &rarr;</a>
                            </div>
                        </div>
                    )}
                </div>

                {/* Stats Column */}
                <div className="stats-column">
                    <div className="mini-stat">
                        <div className="stat-info">
                            <span className="label">Enrolled</span>
                            <div className="value" id="stat-enrolled">{stats?.courses_enrolled || 0}</div>
                        </div>
                        <BookOpen size={24} color="#0E7673" opacity={0.4} />
                    </div>
                    <div className="mini-stat">
                        <div className="stat-info">
                            <span className="label">Completed</span>
                            <div className="value" id="stat-completed">{stats?.courses_completed || 0}</div>
                        </div>
                        <CheckCircle size={24} color="#0E7673" opacity={0.4} />
                    </div>
                    <div className="mini-stat">
                        <div className="stat-info">
                            <span className="label">Orders</span>
                            <div className="value" id="stat-orders">{stats?.total_orders || 0}</div>
                        </div>
                        <ShoppingCart size={24} color="#0E7673" opacity={0.4} />
                    </div>
                </div>
            </div>

            {/* Quick Actions Row */}
            <div className="quick-actions-section" style={{ marginTop: '40px' }}>
                <h3 className="section-title">Jump Back In</h3>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div className="dash-card" style={{ padding: '20px', display: 'flex', alignItems: 'center', gap: '15px' }}>
                        <div style={{ width: '40px', height: '40px', borderRadius: '10px', background: 'rgba(14,118,115,0.1)', display: 'flex', alignItems: 'center', justifyCenter: 'center' }}>
                            <BookOpen size={20} color="#0E7673" />
                        </div>
                        <div>
                            <h4 style={{ margin: 0, fontSize: '15px' }}>Resume Learning</h4>
                            <p style={{ margin: 0, fontSize: '12px', color: 'var(--dash-text-muted)' }}>Pick up where you left off.</p>
                        </div>
                    </div>
                    <div className="dash-card" style={{ padding: '20px', display: 'flex', alignItems: 'center', gap: '15px' }}>
                        <div style={{ width: '40px', height: '40px', borderRadius: '10px', background: 'rgba(14,118,115,0.1)', display: 'flex', alignItems: 'center', justifyCenter: 'center' }}>
                            <ShoppingCart size={20} color="#0E7673" />
                        </div>
                        <div>
                            <h4 style={{ margin: 0, fontSize: '15px' }}>View Latest Order</h4>
                            <p style={{ margin: 0, fontSize: '12px', color: 'var(--dash-text-muted)' }}>Manage your subscriptions.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Overview;
