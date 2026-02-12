import { ClipboardCheck, Download } from 'lucide-react';

const Assessments = ({ data }) => {
    const assessments = data.assessments || [];

    if (!assessments.length) {
        return (
            <div className="dash-card center" style={{ padding: '56px' }}>
                <h3 style={{ color: 'var(--dash-text)', marginBottom: '10px' }}>No Assessment Reports Yet</h3>
                <p className="text-muted">Complete an assessment and your report history will appear here.</p>
                <a href="/free-assessment/" className="smc-btn smc-btn-primary smc-btn-small" style={{ marginTop: '20px' }}>
                    Take Assessment
                </a>
            </div>
        );
    }

    return (
        <div className="assessments-pane">
            <h3 className="section-title">Assessment History</h3>
            <div className="assessment-list">
                {assessments.map((report) => (
                    <article key={report.id} className="assessment-item">
                        <div className="assessment-main">
                            <span className="assessment-icon"><ClipboardCheck size={18} /></span>
                            <div>
                                <h4>{report.quiz_title}</h4>
                                <p>{report.result_title} · {report.date}</p>
                            </div>
                        </div>
                        <div className="assessment-score" style={{ borderColor: report.color || '#0E7673', color: report.color || '#0E7673' }}>
                            {report.score}
                        </div>
                        <a className="smc-btn smc-btn-outline smc-btn-small" href={report.download_url}>
                            <Download size={15} />
                        </a>
                    </article>
                ))}
            </div>
        </div>
    );
};

export default Assessments;
