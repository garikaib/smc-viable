import { useState, useEffect } from '@wordpress/element';
import { LayoutDashboard, Fingerprint, GraduationCap, CreditCard, ClipboardCheck, LogOut } from 'lucide-react';
import Overview from './components/Overview';
import Identity from './components/Identity';
import Learning from './components/Learning';
import Billing from './components/Billing';
import Assessments from './components/Assessments';

const MyAccount = () => {
    const [activeTab, setActiveTab] = useState('overview');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [authRequired, setAuthRequired] = useState(false);

    useEffect(() => {
        fetchProfile();
    }, []);

    useEffect(() => {
        const path = (window.location.pathname || '').replace(/\/+$/, '');
        if (/\/my-account\/(view-order|invoice)\/\d+$/i.test(path)) {
            setActiveTab('billing');
        }
    }, []);

    const fetchProfile = async () => {
        setLoading(true);
        setError(null);
        setAuthRequired(false);
        try {
            const root = window.smcAccountData?.root || '/wp-json/';
            const response = await fetch(`${root}smc/v1/account/profile`, {
                headers: {
                    'X-WP-Nonce': window.smcAccountData?.nonce || ''
                }
            });

            if (!response.ok) {
                if ((response.status === 401 || response.status === 403) && Number(window.smcAccountData?.currentUserId || 0) === 0) {
                    setAuthRequired(true);
                    return;
                }
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

    if (authRequired) {
        const params = new URLSearchParams(window.location.search || '');
        const fromCheckout = params.get('intent') === 'checkout';
        const loginUrl = window.smcAccountData?.loginUrl || '/wp-login.php';
        const registerUrl = window.smcAccountData?.registerUrl || '/wp-login.php?action=register';

        return (
            <div className="smc-my-account-page">
                <div className="smc-container">
                    <div className="smc-dashboard-card smc-state-card">
                        <h2 className="smc-state-error-title">Login Required</h2>
                        <p>
                            {fromCheckout
                                ? 'Please login or register to complete your purchase. Your cart is saved and will still be there after you sign in.'
                                : 'Please login or register to access your account dashboard.'}
                        </p>
                        <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap', justifyContent: 'center' }}>
                            <a href={loginUrl} className="smc-btn smc-btn-primary smc-state-retry-btn">Login</a>
                            <a href={registerUrl} className="smc-btn smc-btn-outline smc-state-retry-btn">Register</a>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    if (loading) {
        return (
            <div className="smc-my-account-page">
                <div className="smc-container">
                    <div className="smc-dashboard-card smc-state-card">
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
                    <div className="smc-dashboard-card smc-state-card">
                        <h2 className="smc-state-error-title">Error</h2>
                        <p>{error}</p>
                        <button onClick={fetchProfile} className="smc-btn smc-btn-primary smc-state-retry-btn">Retry</button>
                    </div>
                </div>
            </div>
        );
    }

    const tabs = [
        { id: 'overview', label: 'Overview', icon: LayoutDashboard },
        { id: 'identity', label: 'Identity', icon: Fingerprint },
        { id: 'learning', label: 'Learning', icon: GraduationCap },
        { id: 'assessments', label: 'Assessments', icon: ClipboardCheck },
        { id: 'billing', label: 'Billing', icon: CreditCard },
    ];

    const planBadgeClass = data.plan?.level === 'standard'
        ? 'plan-bg-premium'
        : (data.plan?.level === 'basic' ? 'plan-bg-basic' : 'plan-bg-free');

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
                            {data.user?.can_manage_assessments && (
                                <a
                                    className="smc-btn smc-btn-primary smc-btn-small assessment-builder-link"
                                    href={data.user?.assessment_center_url || '/assessment-center/'}
                                >
                                    Quiz Builder
                                </a>
                            )}
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
                        {activeTab === 'overview' && <Overview data={data} onNavigateTab={setActiveTab} />}
                        {activeTab === 'identity' && <Identity data={data} onUpdate={fetchProfile} />}
                        {activeTab === 'learning' && <Learning data={data} />}
                        {activeTab === 'assessments' && <Assessments data={data} />}
                        {activeTab === 'billing' && <Billing data={data} />}
                    </main>
                </div>
            </div>
        </div>
    );
};

export default MyAccount;
