export default function MyLearning({ userAccess }) {
    if (!userAccess) {
        return (
            <div style={{ textAlign: 'center', padding: '100px 20px' }}>
                <h2>Restricted Workspace</h2>
                <p>Please log in to your SMC account to access your business science toolkit.</p>
            </div>
        );
    }

    return (
        <div className="learning-dashboard">
            <div style={{
                background: 'linear-gradient(135deg, var(--smc-teal), #0a5c5a)',
                color: 'white',
                padding: '40px',
                borderRadius: '30px',
                marginBottom: '50px',
                boxShadow: '0 20px 50px rgba(14, 118, 115, 0.2)'
            }}>
                <span style={{ fontSize: '11px', fontWeight: '800', letterSpacing: '2px', opacity: 0.8, textTransform: 'uppercase' }}>Current Membership</span>
                <h2 style={{ fontSize: '2.5rem', margin: '10px 0', textTransform: 'capitalize' }}>{userAccess.plan} Access</h2>
                <p style={{ margin: 0, opacity: 0.8 }}>You have full access to all materials associated with your level.</p>
            </div>

            <div className="grid">
                <div style={{ background: '#fff', padding: '40px', borderRadius: '30px', border: '1px solid rgba(0,0,0,0.05)' }}>
                    <h3 style={{ fontSize: '1.5rem', marginBottom: '20px' }}>My Training Tracks</h3>
                    <p style={{ color: '#666', fontStyle: 'italic' }}>Your personalized training modules will appear here as you complete assessments.</p>

                    <div style={{ marginTop: '30px', padding: '30px', background: 'rgba(0,0,0,0.02)', borderRadius: '20px', textAlign: 'center' }}>
                        <p style={{ margin: 0, fontSize: '0.9rem', opacity: 0.5 }}>Module library initializing...</p>
                    </div>
                </div>

                <div style={{ background: '#fff', padding: '40px', borderRadius: '30px', border: '1px solid rgba(0,0,0,0.05)' }}>
                    <h3 style={{ fontSize: '1.5rem', marginBottom: '20px' }}>Strategic Recommendations</h3>
                    <p style={{ color: '#666' }}>We are analyzing your recent assessment data to provide tailormade growth strategies.</p>

                    <div style={{ marginTop: '30px' }}>
                        <div style={{ display: 'flex', gap: '15px', alignItems: 'center', marginBottom: '15px', opacity: 0.4 }}>
                            <div style={{ width: '40px', height: '40px', background: 'var(--smc-teal)', borderRadius: '10px' }}></div>
                            <div style={{ height: '10px', width: '60%', background: '#eee', borderRadius: '5px' }}></div>
                        </div>
                        <div style={{ display: 'flex', gap: '15px', alignItems: 'center', opacity: 0.2 }}>
                            <div style={{ width: '40px', height: '40px', background: 'var(--smc-teal)', borderRadius: '10px' }}></div>
                            <div style={{ height: '10px', width: '40%', background: '#eee', borderRadius: '5px' }}></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
