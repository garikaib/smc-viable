import { useState, useEffect } from '@wordpress/element';
import { LayoutDashboard, Fingerprint, GraduationCap, CreditCard, LogOut } from 'lucide-react';
import Overview from './components/Overview';
import Identity from './components/Identity';
import Learning from './components/Learning';
import Billing from './components/Billing';

const MyAccount = () => {
    const [activeTab, setActiveTab] = useState('overview');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        fetchProfile();
    }, []);

    const fetchProfile = async () => {
        setLoading(true);
        try {
            const root = window.smcAccountData?.root || '/wp-json/';
            const response = await fetch(`${root}smc/v1/account/profile`, {
                headers: {
                    'X-WP-Nonce': window.smcAccountData?.nonce || ''
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch profile: ' + response.status);
            }

            const result = await response.json();
            setData(result);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return (
            <div className="smc-my-account-page">
                <div className="smc-container">
                    <div className="smc-dashboard-card" style={{ padding: '100px', textAlign: 'center' }}>
                        <div className="card-loading">Loading your dashboard...</div>
                    </div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="smc-my-account-page">
                <div className="smc-container">
                    <div className="smc-dashboard-card" style={{ padding: '100px', textAlign: 'center' }}>
                        <h2 style={{ color: '#a1232a' }}>Error</h2>
                        <p>{error}</p>
                        <button onClick={fetchProfile} className="smc-btn smc-btn-primary" style={{ marginTop: '20px' }}>Retry</button>
                    </div>
                </div>
            </div>
        );
    }

    const tabs = [
        { id: 'overview', label: 'Overview', icon: LayoutDashboard },
        { id: 'identity', label: 'Identity', icon: Fingerprint },
        { id: 'learning', label: 'Learning', icon: GraduationCap },
        { id: 'billing', label: 'Billing', icon: CreditCard },
    ];

    const planBadgeClass = data.plan?.level === 'premium' ? 'plan-bg-premium' : (data.plan?.level === 'basic' ? 'plan-bg-basic' : 'plan-bg-free');

    return (
        <div className="smc-my-account-page">
            <div className="smc-container">
                <div className="smc-dashboard-card">
                    {/* Header */}
                    <header className="smc-dash-header">
                        <div className="user-ident">
                            <div className="avatar-wrap">
                                <img src={data.user?.avatar_url} width="64" height="64" alt={data.user?.display_name} />
                            </div>
                            <div className="text-wrap">
                                <h1>{data.user?.display_name}</h1>
                                <span className="member-since">Member since {data.user?.registered_date}</span>
                            </div>
                        </div>
                        <div className="header-meta">
                            <span className={`plan-badge ${planBadgeClass}`}>{data.plan?.label}</span>
                        </div>
                    </header>

                    {/* Tabs */}
                    <nav className="smc-dash-tabs">
                        {tabs.map(tab => {
                            const Icon = tab.icon;
                            return (
                                <button
                                    key={tab.id}
                                    className={`dash-tab ${activeTab === tab.id ? 'active' : ''}`}
                                    onClick={() => setActiveTab(tab.id)}
                                >
                                    <Icon size={18} />
                                    {tab.label}
                                </button>
                            );
                        })}
                        <button
                            className="dash-tab logout"
                            onClick={() => window.location.href = window.smcAccountData?.logoutUrl || '/wp-login.php?action=logout'}
                        >
                            <LogOut size={18} />
                            Logout
                        </button>
                    </nav>

                    {/* Main Content */}
                    <main className="smc-dash-content">
                        {activeTab === 'overview' && <Overview data={data} />}
                        {activeTab === 'identity' && <Identity data={data} onUpdate={fetchProfile} />}
                        {activeTab === 'learning' && <Learning data={data} />}
                        {activeTab === 'billing' && <Billing data={data} />}
                    </main>
                </div>
            </div>
        </div>
    );
};

export default MyAccount;
