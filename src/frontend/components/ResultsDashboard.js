import { useState, useRef, useMemo } from '@wordpress/element';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import jsPDF from 'jspdf';
import { toPng } from 'html-to-image';
import { z } from 'zod';

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

    // Generate PDF Blob - Optimized for smaller file size
    const generatePDF = async () => {
        if (!reportRef.current) return null;
        const element = reportRef.current;

        // Capture with balanced quality/size settings
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
        // Validate with Zod
        if (!validateForm()) {
            return;
        }

        setSendingEmail(true);
        setFormErrors({});

        // Show results immediately - user has provided their email
        setEmailSent(true);

        try {
            // Small delay to ensure the report is rendered and visible
            await new Promise(resolve => setTimeout(resolve, 100));

            // 1. Generate PDF from now-visible report
            const pdf = await generatePDF();
            if (!pdf) throw new Error("PDF Generation Failed - Report element not found");

            const pdfBlob = pdf.output('blob');
            const file = new File([pdfBlob], "report.pdf", { type: "application/pdf" });

            // 2. Send to API
            const formData = new FormData();
            formData.append('quiz_id', quiz.id);
            formData.append('name', leadData.name.trim());
            formData.append('email', leadData.email.trim().toLowerCase());
            formData.append('phone', leadData.phone.trim());
            formData.append('report', file);

            const response = await apiFetch({
                path: '/smc/v1/submit-email',
                method: 'POST',
                body: formData,
            });

            if (response.success) {
                // If email failed but we got a download URL, show it
                if (response.download_url) {
                    alert(__('Your report is ready! If you don\'t receive an email, download it here: ', 'smc-viable') + response.download_url);
                }
            } else {
                throw new Error(response.message || 'Unknown error occurred');
            }
        } catch (e) {
            console.error('Email submission error:', e);

            // Parse API error messages
            let errorMessage = __('Failed to send email. Please try again.', 'smc-viable');

            if (e.code === 'duplicate_email') {
                errorMessage = __('This email has already submitted an assessment. Please use a different email.', 'smc-viable');
                setFormErrors({ email: errorMessage });
            } else if (e.code === 'invalid_email') {
                errorMessage = __('Please enter a valid email address.', 'smc-viable');
                setFormErrors({ email: errorMessage });
            } else if (e.code === 'mail_failed') {
                errorMessage = __('Email delivery failed. Your submission was saved - please contact support.', 'smc-viable');
            } else if (e.message) {
                errorMessage = e.message;
            }

            if (!formErrors.email) {
                alert(errorMessage);
            }
        } finally {
            setSendingEmail(false);
        }
    };

    // Styling Helpers
    // If no config, fallback to defaults
    const getLevelColor = (level) => {
        if (matchingRule) return matchingRule.style?.color ? `text-${matchingRule.style.color}` : 'text-primary';
        // Fallback
        if (level.includes('Strong')) return 'text-success';
        if (level.includes('Moderate')) return 'text-info';
        if (level.includes('Weak')) return 'text-warning';
        return 'text-error';
    };

    const getScoreColorHex = (percent) => {
        // If dynamic, use matchingRule color if mapped? 
        // Rule defines 'color': 'green'. We need hex map.
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
        // Fallback Logic
        ((totalScore / maxPossibleScore) >= 0.8) ? __('Strong readiness!', 'smc-viable') : __('Needs improvement.', 'smc-viable')
    );
    const resultTitle = matchingRule ? matchingRule.condition_text : __('Overall Score', 'smc-viable');

    // -- RENDER --

    // Prevent interaction if currently exporting/sending
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
                                onChange={(val) => {
                                    setLeadData({ ...leadData, name: val });
                                    if (formErrors.name) setFormErrors({ ...formErrors, name: null });
                                }}
                                __next40pxDefaultSize
                                __nextHasNoMarginBottom
                            />
                            {formErrors.name && (
                                <span className="text-error text-sm mt-1">{formErrors.name}</span>
                            )}
                        </div>
                        <div className="form-control">
                            <TextControl
                                label={__('Email Address *', 'smc-viable')}
                                value={leadData.email}
                                onChange={(val) => {
                                    setLeadData({ ...leadData, email: val });
                                    if (formErrors.email) setFormErrors({ ...formErrors, email: null });
                                }}
                                type="email"
                                __next40pxDefaultSize
                                __nextHasNoMarginBottom
                            />
                            {formErrors.email && (
                                <span className="text-error text-sm mt-1">{formErrors.email}</span>
                            )}
                        </div>
                        <div className="form-control">
                            <TextControl
                                label={__('Phone Number', 'smc-viable')}
                                value={leadData.phone}
                                onChange={(val) => {
                                    setLeadData({ ...leadData, phone: val });
                                    if (formErrors.phone) setFormErrors({ ...formErrors, phone: null });
                                }}
                                type="tel"
                                __next40pxDefaultSize
                                __nextHasNoMarginBottom
                            />
                            {formErrors.phone && (
                                <span className="text-error text-sm mt-1">{formErrors.phone}</span>
                            )}
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

                    <div className="alert alert-info shadow-sm mt-6 text-xs flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" className="stroke-current shrink-0 w-6 h-6 mr-2"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>{__('Your information is secure and will not be shared.', 'smc-viable')}</span>
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

                    {/* Top Summary */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="card bg-base-100 shadow border border-base-200">
                            <div className="card-body text-center">
                                <h3 className="card-title justify-center">{resultTitle}</h3>

                                <div className="radial-progress mx-auto my-4 text-primary"
                                    style={{ "--value": (totalScore / maxPossibleScore) * 100, "--size": "8rem", "--thickness": "0.8rem", color: overallColor }}>
                                    <span className="text-3xl font-bold text-base-content">{totalScore}</span>
                                </div>

                                <p className="text-lg leading-relaxed px-4">
                                    {resultMessage}
                                </p>
                                <p className="text-sm text-base-content/60 mt-2">
                                    {__('Total Score:', 'smc-viable')} {totalScore} / {maxPossibleScore}
                                </p>
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

                    {/* Stage Breakdown */}
                    <div>
                        <h3 className="text-xl font-bold mb-4">{__('Stage Breakdown', 'smc-viable')}</h3>
                        <div className="overflow-x-auto bg-base-100 rounded-lg border border-base-200">
                            <table className="table w-full">
                                <thead>
                                    <tr>
                                        <th>{__('Stage', 'smc-viable')}</th>
                                        <th>{__('Score', 'smc-viable')}</th>
                                        <th>{__('Status', 'smc-viable')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {Object.entries(scoresByStage).map(([stageName, data]) => (
                                        <tr key={stageName}>
                                            <td className="font-medium">{stageName}</td>
                                            <td>
                                                <div className="flex items-center gap-2">
                                                    <progress className="progress w-24" value={data.total} max={data.max || 1}
                                                        style={{ color: getScoreColorHex((data.total / (data.max || 1)) * 100) }}
                                                    ></progress>
                                                    <span className="text-xs">{data.total}/{data.max}</span>
                                                </div>
                                            </td>
                                            <td>
                                                {data.flags > 0 ?
                                                    <span className="badge badge-error text-white">{data.flags} Flags</span> :
                                                    <span className="badge badge-ghost">{(data.total / data.max) > 0.5 ? 'Good' : 'Weak'}</span>
                                                }
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Download Action (Always available after gate) */}
                    <div className="flex justify-center pt-8">
                        <Button variant="secondary" onClick={handleDownload} disabled={isBusy}>
                            {isBusy ? __('Processing...', 'smc-viable') : __('Download PDF Report', 'smc-viable')}
                        </Button>
                    </div>
                </>
            )}

            {/* Hidden Report for PDF Generation - positioned off-screen so html-to-image can capture it */}
            <div
                className="fixed w-[210mm] min-h-[297mm] p-10 font-sans bg-white text-black"
                style={{ left: '-9999px', top: 0 }}
                ref={reportRef}
            >
                {/* Header */}
                <div className="flex justify-between items-start pb-4 mb-8 border-b-2" style={{ borderColor: overallColor }}>
                    <h1 className="text-3xl font-bold" style={{ color: overallColor }}>{dashboardConfig?.dashboard_config?.title || quiz.title.rendered}</h1>
                    <p className="text-gray-500">{new Date().toLocaleDateString()}</p>
                </div>

                {/* Summary */}
                <div className="mb-8 p-6 bg-gray-50 rounded-lg">
                    <h2 className="text-xl font-bold mb-2" style={{ color: overallColor }}>{resultTitle}</h2>
                    <p className="text-lg text-gray-700">{resultMessage}</p>
                    <p className="mt-4 font-bold">Total Score: {totalScore} / {maxPossibleScore}</p>
                </div>

                {/* Table */}
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
