import { useState } from '@wordpress/element';
import { User, Building, Lock, Eye, EyeOff, RefreshCw } from 'lucide-react';

const Identity = ({ data, onUpdate }) => {
    const iden = data.business_identity;
    const user = data.user;

    const [personalForm, setPersonalForm] = useState({
        display_name: user?.display_name || '',
        email: user?.email || '',
        current_password: '',
        new_password: '',
    });

    const [businessForm, setBusinessForm] = useState({
        company_name: iden?.company || '',
        industry: iden?.industry || '',
    });

    const [showPassword, setShowPassword] = useState(false);
    const [msg, setMsg] = useState({ type: '', text: '' });
    const [loading, setLoading] = useState(false);

    const handlePersonalSubmit = async (e) => {
        e.preventDefault();

        const payload = {
            display_name: personalForm.display_name,
            email: personalForm.email,
        };

        if (showPassword) {
            if (personalForm.current_password) {
                payload.current_password = personalForm.current_password;
            }
            if (personalForm.new_password) {
                payload.password = personalForm.new_password;
            }
        }

        saveProfile(payload);
    };

    const handleBusinessSubmit = async (e) => {
        e.preventDefault();
        saveProfile({
            company_name: businessForm.company_name,
            industry: businessForm.industry,
        });
    };

    const saveProfile = async (payload) => {
        setLoading(true);
        setMsg({ type: '', text: '' });
        try {
            const response = await fetch('/wp-json/smc/v1/account/profile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.smcAccountData?.nonce || ''
                },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (result.success || !result.code) {
                setMsg({ type: 'success', text: result.message || 'Profile updated successfully.' });
                // Reset password fields if they were successful
                if (showPassword) {
                    setPersonalForm(prev => ({ ...prev, current_password: '', new_password: '' }));
                    setShowPassword(false);
                }
                onUpdate(); // Refresh parent data
            } else {
                setMsg({ type: 'error', text: result.message || 'Failed to update profile.' });
            }
        } catch (err) {
            setMsg({ type: 'error', text: 'Connection error.' });
        } finally {
            setLoading(false);
            setTimeout(() => setMsg({ type: '', text: '' }), 3000);
        }
    };

    return (
        <div className="identity-pane">
            <h3 className="section-title">Identity & Profile</h3>

            <div className="identity-grid">
                {/* Personal Profile */}
                <div className="dash-card">
                    <div className="card-header-iconic">
                        <User size={20} color="#0E7673" />
                        <h3>Personal Identity</h3>
                    </div>
                    <form onSubmit={handlePersonalSubmit}>
                        <div className="form-row">
                            <label>Display Name</label>
                            <input
                                type="text"
                                className="dash-input"
                                value={personalForm.display_name}
                                onChange={(e) => setPersonalForm({ ...personalForm, display_name: e.target.value })}
                            />
                        </div>
                        <div className="form-row">
                            <label>Email Address</label>
                            <input
                                type="email"
                                className="dash-input"
                                value={personalForm.email}
                                onChange={(e) => setPersonalForm({ ...personalForm, email: e.target.value })}
                            />
                        </div>

                        {!showPassword ? (
                            <div className="password-toggle-row">
                                <button
                                    type="button"
                                    className="smc-btn-text"
                                    onClick={() => setShowPassword(true)}
                                >
                                    <Lock size={14} /> Change Security Credentials
                                </button>
                            </div>
                        ) : (
                            <div className="password-fields-section">
                                <div className="section-divider"></div>
                                <div className="form-row">
                                    <label>Current Password</label>
                                    <input
                                        type="password"
                                        className="dash-input"
                                        placeholder="Required for email or pass changes"
                                        value={personalForm.current_password}
                                        onChange={(e) => setPersonalForm({ ...personalForm, current_password: e.target.value })}
                                    />
                                </div>
                                <div className="form-row">
                                    <label>New Password</label>
                                    <input
                                        type="password"
                                        className="dash-input"
                                        placeholder="Leave blank to keep current"
                                        value={personalForm.new_password}
                                        onChange={(e) => setPersonalForm({ ...personalForm, new_password: e.target.value })}
                                    />
                                </div>
                                <button
                                    type="button"
                                    className="smc-btn-text secondary"
                                    onClick={() => setShowPassword(false)}
                                >
                                    Cancel Security Change
                                </button>
                            </div>
                        )}

                        <div className="form-actions">
                            <button type="submit" className="smc-btn smc-btn-primary" disabled={loading}>
                                {loading ? 'Saving...' : 'Save Personal Profile'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Business Profile */}
                <div className="dash-card">
                    <div className="card-header-iconic">
                        <Building size={20} color="#0E7673" />
                        <h3>Business Identity</h3>
                    </div>
                    <form onSubmit={handleBusinessSubmit}>
                        <div className="form-row">
                            <label>Registered Company Name</label>
                            <input
                                type="text"
                                className="dash-input"
                                value={businessForm.company_name}
                                onChange={(e) => setBusinessForm({ ...businessForm, company_name: e.target.value })}
                                placeholder="e.g. SMC Consulting"
                            />
                        </div>
                        <div className="form-row">
                            <label>Primary Industry</label>
                            <input
                                type="text"
                                className="dash-input"
                                value={businessForm.industry}
                                onChange={(e) => setBusinessForm({ ...businessForm, industry: e.target.value })}
                                placeholder="e.g. Strategic Management"
                            />
                        </div>
                        <div className="form-row">
                            <label>Assessed Status</label>
                            <div className={`verification-badge ${iden?.status === 'assessed' ? 'verified' : 'unverified'}`}>
                                <div className="badge-dot"></div>
                                {iden?.status === 'assessed' ? `Verified via Diagnostic on ${iden.date}` : 'Identity Not Yet Assessed'}
                            </div>
                        </div>

                        <div className="form-actions-split">
                            <button type="submit" className="smc-btn smc-btn-primary" disabled={loading}>
                                {loading ? 'Saving...' : 'Update Business Profile'}
                            </button>
                            <a href="/free-assessment/" className="smc-btn-iconic" title="Refresh Assessment">
                                <RefreshCw size={18} />
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {msg.text && (
                <div className={`toast-message ${msg.type}`}>
                    {msg.text}
                </div>
            )}
        </div>
    );
};

export default Identity;
