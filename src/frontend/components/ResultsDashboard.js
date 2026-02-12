import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { ArrowRight, Lock, Unlock, Mail, LogIn, UserPlus } from 'lucide-react';
import { computeQuizScores } from './quizGrading';

export default function ResultsDashboard({ answers, quiz }) {
    const [isSaving, setIsSaving] = useState(false);
    const [savedReport, setSavedReport] = useState(null);
    const [showGuestGate, setShowGuestGate] = useState(false);
    const [guestData, setGuestData] = useState({ name: '', email: '', phone: '' });
    const [guestNotice, setGuestNotice] = useState('');
    const [recommendedCourses, setRecommendedCourses] = useState([]);
    const [unlockedCourses, setUnlockedCourses] = useState([]);

    const settings = quiz.meta?._smc_quiz_settings || {};
    const isLoggedIn = !!window.wpApiSettings?.isLoggedIn;

    const deliverySettings = useMemo(() => {
        return {
            guestPdfAccess: settings?.guest_pdf_access === 'public' ? 'public' : 'account_required',
            loggedInEmailLink: settings?.logged_in_email_link !== false,
            guestEmailCapture: settings?.guest_email_capture !== false
        };
    }, [settings]);

    const dashboardConfig = useMemo(() => {
        try {
            const conf = quiz.meta?._smc_quiz_dashboard_config;
            if (!conf) return null;
            return typeof conf === 'string' ? JSON.parse(conf) : conf;
        } catch (e) {
            return null;
        }
    }, [quiz]);

    const { totalScore, maxPossibleScore, scoresByStage, flaggedItems, scoreData } = useMemo(() => {
        return computeQuizScores(quiz, answers);
    }, [quiz, answers]);

    const matchingRule = useMemo(() => {
        if (!dashboardConfig?.dashboard_config?.rules) return null;
        return dashboardConfig.dashboard_config.rules.find((rule) => {
            const { operator, value, min, max } = rule.logic || {};
            if (operator === 'gt') return totalScore > value;
            if (operator === 'lt') return totalScore < value;
            if (operator === 'between') return totalScore >= min && totalScore <= max;
            if (operator === 'gte') return totalScore >= value;
            if (operator === 'lte') return totalScore <= value;
            return false;
        });
    }, [dashboardConfig, totalScore]);

    const percent = Math.round((totalScore / Math.max(1, maxPossibleScore)) * 100);
    const resultTitle = matchingRule ? matchingRule.condition_text : __('Overall Score', 'smc-viable');
    const resultMessage = matchingRule
        ? matchingRule.message
        : (percent >= 75 ? __('Strong readiness with clear growth momentum.', 'smc-viable') : __('Important areas need operational reinforcement.', 'smc-viable'));

    const stageSummary = useMemo(() => {
        return Object.entries(scoresByStage).map(([stageName, data]) => {
            const p = Math.round((data.total / Math.max(1, data.max)) * 100);
            const tone = p >= 75 ? __('Advanced', 'smc-viable') : (p >= 55 ? __('Growing', 'smc-viable') : __('Foundation', 'smc-viable'));
            const comment = p >= 75
                ? __('You are performing strongly here. Standardize and scale this capability.', 'smc-viable')
                : (p >= 55
                    ? __('Good progress. Add tighter tracking and execution cadence.', 'smc-viable')
                    : __('This area needs immediate focus and structured intervention.', 'smc-viable'));
            return { stageName, ...data, percent: p, tone, comment };
        });
    }, [scoresByStage]);

    const persistReport = async (delivery = 'download') => {
        setIsSaving(true);
        setGuestNotice('');
        try {
            const payload = {
                quiz_id: quiz.id,
                name: guestData.name,
                email: guestData.email,
                phone: guestData.phone,
                delivery,
                score_data: {
                    ...scoreData,
                    total_score: totalScore,
                    scores_by_stage: scoresByStage,
                    answers: answers || {}
                }
            };

            const response = await apiFetch({
                path: '/smc/v1/report/save',
                method: 'POST',
                data: payload
            });

            setSavedReport(response);
            setRecommendedCourses(response.recommended_courses || []);
            setUnlockedCourses(response.enrolled_courses || []);
            return response;
        } catch (e) {
            setGuestNotice(e?.message || __('Could not save your report. Please try again.', 'smc-viable'));
            return null;
        } finally {
            setIsSaving(false);
        }
    };

    const handleDownload = async () => {
        if (isLoggedIn || deliverySettings.guestPdfAccess === 'public') {
            const report = savedReport || await persistReport('download');
            if (report?.download_url) {
                window.location.href = report.download_url;
            }
            return;
        }

        setShowGuestGate(true);
        setGuestNotice(__('Create an account or log in to unlock your PDF. Add your email below so we can retain your report.', 'smc-viable'));
    };

    const handleLoggedInEmailLink = async () => {
        const report = await persistReport('email_link');
        if (report?.email_sent) {
            setGuestNotice(__('We emailed your secure report download link.', 'smc-viable'));
        } else if (report?.email_error) {
            setGuestNotice(report.email_error);
        }
    };

    const handleGuestEmail = async () => {
        if (!guestData.email) {
            setGuestNotice(__('Please provide your email so we can send your report link.', 'smc-viable'));
            return;
        }
        const report = await persistReport('email_guest');
        if (report?.email_sent) {
            setGuestNotice(__('Report link sent. Check your inbox.', 'smc-viable'));
        } else if (report?.email_error) {
            setGuestNotice(report.email_error);
        }
    };

    const handleGuestAuthRedirect = async (target) => {
        if (!guestData.email) {
            setShowGuestGate(true);
            setGuestNotice(__('Enter your email first so we can save and link this report to your new account.', 'smc-viable'));
            return;
        }

        const report = savedReport || await persistReport('account_required');
        if (!report) return;

        const fallbackLogin = window.wpApiSettings?.loginUrl || '/wp-login.php';
        const fallbackRegister = window.wpApiSettings?.registerUrl || '/wp-login.php?action=register';
        const url = target === 'register' ? (report.register_url || fallbackRegister) : (report.login_url || fallbackLogin);
        window.location.href = url;
    };

    return (
        <div className="smc-results-dashboard smc-results-premium animate-fade-in">
            <div className="smc-results-header">
                <div>
                    <p className="eyebrow">{__('Business Assessment', 'smc-viable')}</p>
                    <h2>{quiz.title.rendered}</h2>
                    <p>{resultMessage}</p>
                </div>
                <div className="actions smc-results-actions">
                    <button className="btn-download" onClick={handleDownload} disabled={isSaving}>
                        {isLoggedIn || deliverySettings.guestPdfAccess === 'public'
                            ? __('Download Full PDF', 'smc-viable')
                            : __('Login / Register To Download PDF', 'smc-viable')}
                        <ArrowRight size={16} />
                    </button>
                    {isLoggedIn && deliverySettings.loggedInEmailLink && (
                        <button className="btn-download btn-secondary" onClick={handleLoggedInEmailLink} disabled={isSaving}>
                            {__('Email Download Link', 'smc-viable')}
                            <Mail size={16} />
                        </button>
                    )}
                </div>
            </div>

            {!isLoggedIn && (showGuestGate || deliverySettings.guestPdfAccess === 'account_required') && (
                <div className="smc-guest-gate">
                    <h3>{__('Save & Access Your Report', 'smc-viable')}</h3>
                    <div className="guest-grid">
                        <input
                            type="text"
                            placeholder={__('Full name', 'smc-viable')}
                            value={guestData.name}
                            onChange={(e) => setGuestData({ ...guestData, name: e.target.value })}
                        />
                        <input
                            type="email"
                            placeholder={__('Email address *', 'smc-viable')}
                            value={guestData.email}
                            onChange={(e) => setGuestData({ ...guestData, email: e.target.value })}
                        />
                        <input
                            type="tel"
                            placeholder={__('Phone (optional)', 'smc-viable')}
                            value={guestData.phone}
                            onChange={(e) => setGuestData({ ...guestData, phone: e.target.value })}
                        />
                        {deliverySettings.guestEmailCapture ? (
                            <button onClick={handleGuestEmail} disabled={isSaving}>
                                {isSaving ? __('Sending...', 'smc-viable') : __('Email My Report Link', 'smc-viable')}
                            </button>
                        ) : (
                            <button onClick={() => handleGuestAuthRedirect('login')} disabled={isSaving}>
                                {isSaving ? __('Saving...', 'smc-viable') : __('Save Report', 'smc-viable')}
                            </button>
                        )}
                    </div>
                    <div className="guest-auth-cta">
                        <button type="button" onClick={() => handleGuestAuthRedirect('login')} disabled={isSaving}>
                            <LogIn size={15} />
                            <span>{__('Continue to Login', 'smc-viable')}</span>
                        </button>
                        <button type="button" onClick={() => handleGuestAuthRedirect('register')} disabled={isSaving}>
                            <UserPlus size={15} />
                            <span>{__('Create Account', 'smc-viable')}</span>
                        </button>
                    </div>
                    {guestNotice && <p className="guest-notice">{guestNotice}</p>}
                </div>
            )}

            {!isLoggedIn && !showGuestGate && deliverySettings.guestPdfAccess === 'public' && guestNotice && (
                <p className="guest-notice">{guestNotice}</p>
            )}

            <div className="smc-score-grid">
                <div className="score-card">
                    <h3>{resultTitle}</h3>
                    <div className="score-ring">
                        <span>{percent}%</span>
                        <small>{totalScore} / {maxPossibleScore}</small>
                    </div>
                </div>
                <div className="analysis-card">
                    <h3>{__('Critical Analysis', 'smc-viable')}</h3>
                    {flaggedItems.length > 0 ? (
                        <>
                            <div className="critical-banner">{flaggedItems.length} {__('critical red flags identified.', 'smc-viable')}</div>
                            <ul>
                                {flaggedItems.map((item, idx) => (
                                    <li key={idx}>{item.stage}: {item.score}%</li>
                                ))}
                            </ul>
                        </>
                    ) : (
                        <div className="safe-banner">{__('No critical red flags detected in this run.', 'smc-viable')}</div>
                    )}
                </div>
            </div>

            <div className="stage-grid">
                {stageSummary.map((stage) => (
                    <article key={stage.stageName} className="stage-card">
                        <header>
                            <h4>{stage.stageName}</h4>
                            <span>{stage.percent}%</span>
                        </header>
                        <div className="bar">
                            <div className="fill" style={{ width: `${stage.percent}%` }}></div>
                        </div>
                        <p className="tone">{stage.tone}</p>
                        <p>{stage.comment}</p>
                    </article>
                ))}
            </div>

            {(unlockedCourses.length > 0 || recommendedCourses.length > 0) && (
                <div className="smc-outcomes">
                    {unlockedCourses.length > 0 && (
                        <div className="outcome unlocked">
                            <h4><Unlock size={16} /> {__('Unlocked Courses', 'smc-viable')}</h4>
                            <p>{__('You now have additional learning modules available in your LMS dashboard.', 'smc-viable')}</p>
                        </div>
                    )}
                    {recommendedCourses.length > 0 && (
                        <div className="outcome recommended">
                            <h4><Lock size={16} /> {__('Recommended Next Courses', 'smc-viable')}</h4>
                            <ul>
                                {recommendedCourses.map((c, i) => (
                                    <li key={i}>{c.course_title || __('Advanced Modules', 'smc-viable')}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
