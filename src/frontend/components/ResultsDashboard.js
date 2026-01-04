import { useState, useRef, useMemo } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import jsPDF from 'jspdf';
import { toPng } from 'html-to-image';

export default function ResultsDashboard({ answers, quiz }) {
    const reportRef = useRef(null);
    const [isExporting, setIsExporting] = useState(false);

    const questions = quiz.meta._smc_quiz_questions || [];

    // Calculations
    const { totalScore, maxPossibleScore, readinessRating, scoresByStage, flaggedItems } = useMemo(() => {
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
                // Determine value/score
                let val = null;
                if (q.type === 'scorable') {
                    val = parseInt(answers[q.id]);
                } else if (q.type === 'select') {
                    // Find score from options
                    const selectedOpt = (q.options || []).find(o => (typeof o === 'object' ? o.label : o) === answers[q.id]);
                    if (selectedOpt && typeof selectedOpt === 'object' && selectedOpt.score !== undefined) {
                        val = selectedOpt.score;
                    } // else, no score (e.g. legacy string option or score 0)
                }

                if (val !== null && !isNaN(val)) {
                    total += val;
                    stageGroups[stage].total += val;

                    // Max score per item is 15 (Great)
                    max += 15;
                    stageGroups[stage].max += 15;

                    // Flag check
                    if (val === -5) {
                        flags.push(q);
                        stageGroups[stage].flags++;
                    }
                }
            }
        });

        // Readiness Rating
        let percent = 0;
        let level = 'Pending';
        if (max > 0) {
            percent = Math.round((total / max) * 100);
            if (percent >= 80) level = __('Strong readiness', 'smc-viable');
            else if (percent >= 60) level = __('Moderate readiness', 'smc-viable');
            else if (percent >= 40) level = __('Weak readiness', 'smc-viable');
            else level = __('Critical gaps', 'smc-viable');
        }

        return {
            totalScore: total,
            maxPossibleScore: max,
            readinessRating: { percent, level },
            scoresByStage: stageGroups,
            flaggedItems: flags
        };
    }, [answers, questions]);

    const exportPDF = async () => {
        if (!reportRef.current) return;
        setIsExporting(true);

        try {
            const element = reportRef.current;

            // html-to-image: More robust handling of modern CSS
            const dataUrl = await toPng(element, { quality: 0.95, backgroundColor: '#ffffff' });

            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });

            const imgProps = pdf.getImageProperties(dataUrl);
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

            pdf.addImage(dataUrl, 'PNG', 0, 0, pdfWidth, pdfHeight);
            pdf.save(`SMC_Assessment.pdf`);
        } catch (error) {
            console.error('Export failed', error);
            alert(__('Failed to generate PDF.', 'smc-viable'));
        } finally {
            setIsExporting(false);
        }
    };

    const getLevelColor = (level) => {
        if (level.includes('Strong')) return 'text-success';
        if (level.includes('Moderate')) return 'text-info';
        if (level.includes('Weak')) return 'text-warning';
        return 'text-error';
    };

    const getBadgeClass = (score, max) => {
        const ratio = score / max;
        if (ratio < 0.5) return 'badge-warning';
        return 'badge-success';
    };

    return (
        <div className="smc-results-dashboard animate-fade-in space-y-8">

            {/* Top Summary */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">

                {/* Overall Readiness Card */}
                <div className="card bg-base-100 shadow border border-base-200">
                    <div className="card-body text-center">
                        <h3 className="card-title justify-center">{__('Overall Readiness', 'smc-viable')}</h3>

                        <div className="radial-progress mx-auto my-4 text-primary"
                            style={{ "--value": readinessRating.percent, "--size": "8rem", "--thickness": "0.8rem" }}>
                            <span className="text-3xl font-bold text-base-content">{readinessRating.percent}%</span>
                        </div>

                        <div className={`text-xl font-bold ${getLevelColor(readinessRating.level)}`}>
                            {readinessRating.level}
                        </div>
                        <p className="text-sm text-base-content/60">
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
                                    <svg xmlns="http://www.w3.org/2000/svg" className="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    <span>{flaggedItems.length} {__('Critical Red Flags found.', 'smc-viable')}</span>
                                </div>
                                <ul className="list-disc list-inside text-sm space-y-1">
                                    {flaggedItems.map((item, idx) => (
                                        <li key={idx} className="text-error font-medium">
                                            {item.indicator || item.text}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : (
                            <div className="alert alert-success shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" className="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
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
                                <th>{__('Items', 'smc-viable')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(scoresByStage).map(([stageName, data]) => (
                                <tr key={stageName}>
                                    <td className="font-medium">{stageName}</td>
                                    <td>
                                        <div className="flex items-center gap-2">
                                            <progress className="progress progress-primary w-24" value={data.total} max={data.max || 1}></progress>
                                            <span className="text-xs">{data.total}/{data.max}</span>
                                        </div>
                                    </td>
                                    <td>
                                        {data.flags > 0 ? (
                                            <span className="badge badge-error gap-1 text-white">
                                                {data.flags} {__('Flags', 'smc-viable')}
                                            </span>
                                        ) : (
                                            <span className={`badge ${getBadgeClass(data.total, data.max)} text-white`}>
                                                {data.total / (data.max || 1) < 0.5 ? __('Weak', 'smc-viable') : __('Good', 'smc-viable')}
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-xs text-base-content/60">
                                        {data.items.length} {__('indicators', 'smc-viable')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Actions */}
            <div className="flex justify-center pt-8">
                <Button variant="secondary" onClick={exportPDF} disabled={isExporting}>
                    {isExporting ? __('Generating...', 'smc-viable') : __('Download PDF Report', 'smc-viable')}
                </Button>
            </div>

            {/* Hidden Report for PDF Generation - using inline HEX colors for html2canvas compatibility
                Using z-index and absolute positioning instead of far-off-screen to ensure rendering.
             */}
            <div
                className="absolute top-0 left-0 w-[210mm] min-h-[297mm] p-10 font-sans bg-white text-black -z-50 invisible"
                ref={reportRef}
                style={{ visibility: isExporting ? 'visible' : 'hidden' }} // Only make visible to DOM/Capturer during export if needed, or keep 'visible' but behind. 
            // html-to-image needs it to be rendered. 'visibility: hidden' might block it.
            // Let's try z-index -50 and opacity 0? No, opacity might affect capture.
            // Best: Absolute 0,0, z-index -50.
            >
                <div className="flex justify-between items-start pb-4 mb-8 border-b-2" style={{ borderColor: '#b91c1c' }}>
                    <h1 className="text-3xl font-bold" style={{ color: '#15803d' }}>{__('Readiness Report', 'smc-viable')}</h1>
                    <p className="text-gray-500">{new Date().toLocaleDateString()}</p>
                </div>

                <div className="flex items-center gap-8 mb-8">
                    <div className="w-1/3 text-center">
                        <div className="mx-auto flex items-center justify-center rounded-full border-8 h-24 w-24" style={{ borderColor: '#15803d' }}>
                            <span className="text-2xl font-bold">{readinessRating.percent}%</span>
                        </div>
                        <div className="text-sm font-bold mt-2 uppercase text-gray-600">{__('Readiness Score', 'smc-viable')}</div>
                    </div>
                    <div className="w-2/3">
                        <h2 className="text-2xl font-bold" style={{ color: readinessRating.level.includes('Critical') ? '#b91c1c' : '#15803d' }}>
                            {readinessRating.level}
                        </h2>
                        <p className="mt-1 text-gray-600">
                            {__('Based on a total score of', 'smc-viable')} {totalScore} / {maxPossibleScore}.
                        </p>
                    </div>
                </div>

                {/* PDF Breakdown Table */}
                <div className="mb-8">
                    <h3 className="text-lg font-bold mb-4 pb-1 border-b border-gray-200" style={{ color: '#15803d' }}>{__('Stage Breakdown', 'smc-viable')}</h3>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left bg-gray-100">
                                <th className="p-2">{__('Stage', 'smc-viable')}</th>
                                <th className="p-2">{__('Score', 'smc-viable')}</th>
                                <th className="p-2">{__('Status', 'smc-viable')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(scoresByStage).map(([stageName, data]) => (
                                <tr key={stageName} className="border-b border-gray-100">
                                    <td className="p-2 font-medium">{stageName}</td>
                                    <td className="p-2">{data.total}/{data.max}</td>
                                    <td className="p-2">
                                        {data.flags > 0 ?
                                            <span className="font-bold" style={{ color: '#b91c1c' }}>{data.flags} Flags</span> :
                                            (data.total / data.max < 0.5 ? <span className="font-bold" style={{ color: '#ca8a04' }}>Weak</span> : <span className="font-bold" style={{ color: '#15803d' }}>Good</span>)
                                        }
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="mt-auto pt-8 text-center text-xs text-gray-500 border-t border-gray-200">
                    <p>{__('Social Marketing Centre Assessment', 'smc-viable')}</p>
                </div>
            </div>

        </div>
    );
}
