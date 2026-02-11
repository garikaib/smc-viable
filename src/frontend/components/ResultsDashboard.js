import { useState, useRef, useMemo } from '@wordpress/element';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import jsPDF from 'jspdf';
import { toPng } from 'html-to-image';
import { z } from 'zod';
import { ArrowRight, Unlock, Lock } from 'lucide-react';

// Zod Validation Schema
const leadFormSchema = z.object({
    name: z.string()
        .min(2, 'Name must be at least 2 characters')
        .max(100, 'Name is too long')
        .regex(/^[a-zA-Z\s'-]+$/, 'Name can only contain letters, spaces, hyphens and apostrophes'),
    email: z.string()
        .email('Please enter a valid email address')
        .max(255, 'Email is too long'),
    phone: z.string()
        .optional()
        .refine(val => !val || /^[\d\s+\-()]{7,20}$/.test(val), 'Please enter a valid phone number')
});

export default function ResultsDashboard({ answers, quiz }) {
    const reportRef = useRef(null);
    const [isExporting, setIsExporting] = useState(false);

    // Email Flow States
    const [leadData, setLeadData] = useState({ name: '', email: '', phone: '' });
    const [sendingEmail, setSendingEmail] = useState(false);
    const [emailSent, setEmailSent] = useState(false);
    const [formErrors, setFormErrors] = useState({});

    // Outcome States
    const [unlockedCourses, setUnlockedCourses] = useState([]);
    const [recommendedCourses, setRecommendedCourses] = useState([]);
    const [requiresLogin, setRequiresLogin] = useState(false);

    const questions = quiz.meta?._smc_quiz_questions || [];
    const settings = quiz.meta?._smc_quiz_settings || {};

    // Parse Dashboard Config
    const dashboardConfig = useMemo(() => {
        try {
            const conf = quiz.meta?._smc_quiz_dashboard_config;
            if (!conf) return null;
            return typeof conf === 'string' ? JSON.parse(conf) : conf;
        } catch (e) {
            console.error("Invalid Dashboard Config", e);
            return null;
        }
    }, [quiz]);

    const isEmailMode = settings.delivery_mode === 'email';

    // Calculations
    const { totalScore, maxPossibleScore, scoresByStage, flaggedItems } = useMemo(() => {
        let total = 0;
        let max = 0;
        const stageGroups = {};
        const flags = [];

        questions.forEach(q => {
            // Group setup
            const stage = q.stage || 'Other';
            if (!stageGroups[stage]) {
                stageGroups[stage] = { total: 0, max: 0, flags: 0, items: [] };
            }
            stageGroups[stage].items.push(q);

            // Scoring
            if ((q.type === 'scorable' || q.type === 'select') && answers[q.id] !== undefined) {
                let val = null;
                if (q.type === 'scorable') {
                    val = parseInt(answers[q.id]);
                } else if (q.type === 'select') {
                    const selectedOpt = (q.options || []).find(o => (typeof o === 'object' ? o.label : o) === answers[q.id]);
                    if (selectedOpt && typeof selectedOpt === 'object' && selectedOpt.score !== undefined) {
                        val = selectedOpt.score;
                    }
                }

                if (val !== null && !isNaN(val)) {
                    total += val;
                    stageGroups[stage].total += val;
                    max += 15;
                    stageGroups[stage].max += 15;

                    if (val === -5) {
                        flags.push(q);
                        stageGroups[stage].flags++;
                    }
                }
            }
        });

        return {
            totalScore: total,
            maxPossibleScore: max,
            scoresByStage: stageGroups,
            flaggedItems: flags
        };
    }, [answers, questions]);

    // Rule Evaluation
    const matchingRule = useMemo(() => {
        if (!dashboardConfig?.dashboard_config?.rules) return null;

        return dashboardConfig.dashboard_config.rules.find(rule => {
            const { operator, value, min, max } = rule.logic;
            if (operator === 'gt') return totalScore > value;
            if (operator === 'lt') return totalScore < value;
            if (operator === 'between') return totalScore >= min && totalScore <= max;
            if (operator === 'gte') return totalScore >= value;
            if (operator === 'lte') return totalScore <= value;
            return false;
        });
    }, [totalScore, dashboardConfig]);

    // Generate PDF Blob
    const generatePDF = async () => {
        if (!reportRef.current) return null;
        const element = reportRef.current;

        const dataUrl = await toPng(element, {
            quality: 0.8,
            backgroundColor: '#ffffff',
            pixelRatio: 1.5
        });

        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4',
            compress: true
        });

        const imgProps = pdf.getImageProperties(dataUrl);
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

        pdf.addImage(dataUrl, 'JPEG', 0, 0, pdfWidth, pdfHeight, undefined, 'MEDIUM');
        return pdf;
    };

    const handleDownload = async () => {
        setIsExporting(true);
        try {
            const pdf = await generatePDF();
            if (pdf) pdf.save(`${quiz.title.rendered || 'Assessment'}.pdf`);
        } catch (e) {
            console.error(e);
            alert(__('Failed to generate PDF', 'smc-viable'));
        } finally {
            setIsExporting(false);
        }
    };

    const validateForm = () => {
        try {
            leadFormSchema.parse(leadData);
            setFormErrors({});
            return true;
        } catch (error) {
            if (error instanceof z.ZodError) {
                const errors = {};
                error.errors.forEach(err => {
                    errors[err.path[0]] = err.message;
                });
                setFormErrors(errors);
            }
            return false;
        }
    };

    const handleEmailSubmit = async () => {
        if (!validateForm()) return;

        setSendingEmail(true);
        setFormErrors({});
        setEmailSent(true);

        try {
            await new Promise(resolve => setTimeout(resolve, 100));

            const pdf = await generatePDF();
            if (!pdf) throw new Error("PDF Generation Failed");

            const pdfBlob = pdf.output('blob');
            const file = new File([pdfBlob], "report.pdf", { type: "application/pdf" });

            const formData = new FormData();
            formData.append('quiz_id', quiz.id);
            formData.append('name', leadData.name.trim());
            formData.append('email', leadData.email.trim().toLowerCase());
            formData.append('phone', leadData.phone.trim());
            formData.append('report', file);

            // Send Score Data
            formData.append('score_data', JSON.stringify({
                total_score: totalScore,
                scores_by_stage: scoresByStage
            }));

            const response = await apiFetch({
                path: '/smc/v1/quizzes/submit-email', // Ensure correct route base
                method: 'POST',
                body: formData,
            });

            if (response.success) {
                // Handle Enrollment Outcomes
                if (response.enrolled_courses && response.enrolled_courses.length > 0) {
                    setUnlockedCourses(response.enrolled_courses);
                }
                if (response.recommended_courses && response.recommended_courses.length > 0) {
                    setRecommendedCourses(response.recommended_courses);
                }
                if (response.requires_login) {
                    setRequiresLogin(true);
                }

                if (response.download_url) {
                    alert(__('Your report is ready! If you don\'t receive an email, download it here: ', 'smc-viable') + response.download_url);
                }
            } else {
                throw new Error(response.message || 'Unknown error occurred');
            }
        } catch (e) {
            console.error('Email submission error:', e);
            // Error handling (keep existing logic or simplify)
            let errorMessage = __('Failed to send email. Please try again.', 'smc-viable');
            if (e.code === 'duplicate_email') errorMessage = __('This email has already submitted an assessment.', 'smc-viable');
            alert(errorMessage);
        } finally {
            setSendingEmail(false);
        }
    };

    // Styling Helpers
    const getScoreColorHex = (percent) => {
        if (matchingRule) {
            const map = { green: '#16a34a', 'light-green': '#4ade80', orange: '#d97706', red: '#dc2626' };
            return map[matchingRule.style?.color] || '#0284c7';
        }
        if (percent >= 80) return '#16a34a';
        if (percent >= 60) return '#0284c7';
        if (percent >= 40) return '#d97706';
        return '#dc2626';
    };

    const overallColor = getScoreColorHex((totalScore / maxPossibleScore) * 100);
    const resultMessage = matchingRule ? matchingRule.message : (
        ((totalScore / maxPossibleScore) >= 0.8) ? __('Strong readiness!', 'smc-viable') : __('Needs improvement.', 'smc-viable')
    );
    const resultTitle = matchingRule ? matchingRule.condition_text : __('Overall Score', 'smc-viable');

    // -- RENDER --
    const isBusy = isExporting || sendingEmail;

    return (
        <div className="smc-results-dashboard animate-fade-in space-y-8 relative">

            {/* Email Gate / Lead Form */}
            {isEmailMode && !emailSent && (
                <div className="max-w-md mx-auto bg-base-100 p-8 rounded-xl border-t-8 border-primary shadow-xl">
                    <h2 className="text-2xl font-bold mb-4 text-center text-base-content">{__('Enter your details to get your results', 'smc-viable')}</h2>
                    <p className="text-base-content/60 mb-6 text-center">{__('We will email you the full PDF report immediately.', 'smc-viable')}</p>

                    <div className="text-left space-y-4">
                        <div className="form-control">
                            <TextControl
                                label={__('Full Name *', 'smc-viable')}
                                value={leadData.name}
                                onChange={(val) => setLeadData({ ...leadData, name: val })}
                            />
                        </div>
                        <div className="form-control">
                            <TextControl
                                label={__('Email Address *', 'smc-viable')}
                                value={leadData.email}
                                onChange={(val) => setLeadData({ ...leadData, email: val })}
                                type="email"
                            />
                        </div>
                        <div className="form-control">
                            <TextControl
                                label={__('Phone Number', 'smc-viable')}
                                value={leadData.phone}
                                onChange={(val) => setLeadData({ ...leadData, phone: val })}
                                type="tel"
                            />
                        </div>
                    </div>

                    <div className="mt-8">
                        <button
                            className="btn btn-primary w-full text-lg h-auto py-3 shadow-md hover:shadow-lg transition-all"
                            onClick={handleEmailSubmit}
                            disabled={isBusy}
                        >
                            {isBusy ? <span className="loading loading-spinner"></span> : null}
                            {__('Get My Results', 'smc-viable')}
                        </button>
                    </div>
                </div>
            )}

            {/* Dashboard (Shown if Download Mode OR Email Sent) */}
            {(!isEmailMode || emailSent) && (
                <>
                    {emailSent && (
                        <div className="alert alert-success shadow-lg mb-8">
                            <div>
                                <svg xmlns="http://www.w3.org/2000/svg" className="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>{__('Success! Your report has been emailed.', 'smc-viable')}</span>
                            </div>
                        </div>
                    )}

                    {/* ENROLLMENT OUTCOMES */}
                    {(unlockedCourses.length > 0 || recommendedCourses.length > 0) && (
                        <div className="grid gap-6 mb-8">
                            {unlockedCourses.length > 0 && (
                                <div className="bg-green-50 border border-green-200 p-6 rounded-lg text-green-900">
                                    <h3 className="flex items-center gap-2 font-bold text-lg mb-2">
                                        <Unlock size={20} /> Modules Unlocked!
                                    </h3>
                                    <p>Based on your score, you have been enrolled in:</p>
                                    <ul className="list-disc list-inside mt-2 font-bold">
                                        {/* We only have IDs, so generic message unless we return titles. 
                                            Let's assume backend sends IDs. 
                                            Actually backend returns just IDs in enrolled_courses usually.
                                            But in evaluate_quiz_rules/process_quiz_enrollment we return objects? 
                                            Wait, process_quiz_enrollment returns array of IDs.
                                            We should probably improve backend to return titles, or just say "Exclusive Content".
                                            For now, just a generic success message.
                                         */}
                                        <li>Exclusive Course Content</li>
                                    </ul>
                                    <a href="/student-hub" className="btn btn-sm btn-success mt-4">Go to Student Hub</a>
                                </div>
                            )}

                            {recommendedCourses.length > 0 && requiresLogin && (
                                <div className="bg-blue-50 border border-blue-200 p-6 rounded-lg text-blue-900">
                                    <h3 className="flex items-center gap-2 font-bold text-lg mb-2">
                                        <Lock size={20} /> Recommended for You
                                    </h3>
                                    <p>Your results suggest you would benefit from:</p>
                                    <ul className="list-disc list-inside mt-2 font-bold">
                                        {recommendedCourses.map((c, i) => (
                                            <li key={i}>{c.course_title || 'Advanced Modules'}</li>
                                        ))}
                                    </ul>
                                    <p className="mt-2 text-sm">Log in or upgrade your plan to access these modules.</p>
                                    <a href="/my-account" className="btn btn-sm btn-primary mt-4">Log In / Sign Up</a>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Top Summary */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="card bg-base-100 shadow border border-base-200">
                            <div className="card-body text-center">
                                <h3 className="card-title justify-center">{resultTitle}</h3>
                                <div className="radial-progress mx-auto my-4 text-primary"
                                    style={{ "--value": (totalScore / maxPossibleScore) * 100, "--size": "8rem", "--thickness": "0.8rem", color: overallColor }}>
                                    <span className="text-3xl font-bold text-base-content">{totalScore}</span>
                                </div>
                                <p className="text-lg leading-relaxed px-4">{resultMessage}</p>
                            </div>
                        </div>

                        {/* Analysis Card */}
                        <div className="card bg-base-100 shadow border border-base-200">
                            <div className="card-body">
                                <h3 className="card-title">{__('Analysis', 'smc-viable')}</h3>
                                {flaggedItems.length > 0 ? (
                                    <div>
                                        <div className="alert alert-error shadow-sm mb-4">
                                            <span>{flaggedItems.length} {__('Critical Red Flags found.', 'smc-viable')}</span>
                                        </div>
                                        <ul className="list-disc list-inside text-sm space-y-1">
                                            {flaggedItems.map((item, idx) => (
                                                <li key={idx} className="text-error font-medium">{item.indicator || item.text}</li>
                                            ))}
                                        </ul>
                                    </div>
                                ) : (
                                    <div className="alert alert-success shadow-sm">
                                        <span>{__('No critical flags identified!', 'smc-viable')}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </>
            )}

            {/* Hidden Report for PDF Generation */}
            <div
                className="fixed w-[210mm] min-h-[297mm] p-10 font-sans bg-white text-black"
                style={{ left: '-9999px', top: 0 }}
                ref={reportRef}
            >
                <div className="flex justify-between items-start pb-4 mb-8 border-b-2" style={{ borderColor: overallColor }}>
                    <h1 className="text-3xl font-bold" style={{ color: overallColor }}>{dashboardConfig?.dashboard_config?.title || quiz.title.rendered}</h1>
                    <p className="text-gray-500">{new Date().toLocaleDateString()}</p>
                </div>
                <div className="mb-8 p-6 bg-gray-50 rounded-lg">
                    <h2 className="text-xl font-bold mb-2" style={{ color: overallColor }}>{resultTitle}</h2>
                    <p className="text-lg text-gray-700">{resultMessage}</p>
                    <p className="mt-4 font-bold">Total Score: {totalScore} / {maxPossibleScore}</p>
                </div>
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left bg-gray-100">
                            <th className="p-2">Stage</th>
                            <th className="p-2">Score</th>
                            <th className="p-2">Flags</th>
                        </tr>
                    </thead>
                    <tbody>
                        {Object.entries(scoresByStage).map(([stageName, data]) => (
                            <tr key={stageName} className="border-b border-gray-100">
                                <td className="p-2 font-bold">{stageName}</td>
                                <td className="p-2">{data.total} / {data.max}</td>
                                <td className="p-2 text-red-600">{data.flags > 0 ? `${data.flags} Flags` : '-'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div className="mt-8 text-xs text-gray-400 text-center">Generated by {getScoreColorHex(0) ? 'SMC Viable' : ''}</div>
            </div>
        </div>
    );
}
