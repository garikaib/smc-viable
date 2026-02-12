import { Copy, Trash2 } from 'lucide-react';

const QUESTION_TYPES = [
    { label: 'Single Choice (Scored)', value: 'single_choice' },
    { label: 'Multi Select (Partial Credit)', value: 'multi_select' },
    { label: 'Numeric (Tolerance)', value: 'numeric' },
    { label: 'Ranking / Ordering', value: 'ranking' },
    { label: 'Matching Pairs', value: 'matching' },
    { label: 'Matrix True/False', value: 'matrix_true_false' },
    { label: 'Short Text (Ungraded)', value: 'short_text' },
    { label: 'Date Month (Ungraded)', value: 'date_month' }
];

const LEADING_LANGUAGE = /(obviously|clearly|of course|best answer|you should select|correct answer is)/i;

const uid = (prefix) => `${prefix}_${Math.random().toString(36).slice(2, 8)}`;

const ensureArray = (value) => (Array.isArray(value) ? value : []);

const normalizeQuestionForEditor = (question) => {
    const type = question?.type || 'single_choice';
    const base = {
        ...question,
        type,
        stage: question?.stage || '',
        indicator: question?.indicator || '',
        text: question?.text || '',
        key_text: question?.key_text || '',
        guidance: question?.guidance || '',
        grading: typeof question?.grading === 'object' && question.grading ? question.grading : {},
    };

    if (type === 'single_choice' || type === 'multi_select') {
        base.choices = ensureArray(question?.choices).map((choice, index) => ({
            id: choice?.id || uid(`choice_${index + 1}`),
            label: choice?.label || '',
            points: Number(choice?.points || 0),
        }));
        if (!base.choices.length) {
            base.choices = [
                { id: uid('choice'), label: 'Option 1', points: 0 },
                { id: uid('choice'), label: 'Option 2', points: 0 },
            ];
        }
    }

    if (type === 'numeric') {
        base.numeric = {
            correct_value: Number(question?.numeric?.correct_value || 0),
            tolerance: Number(question?.numeric?.tolerance || 0),
            min: question?.numeric?.min ?? '',
            max: question?.numeric?.max ?? '',
            unit: question?.numeric?.unit || '',
        };
    }

    if (type === 'ranking') {
        const items = ensureArray(question?.ranking?.items).map((item, index) => ({
            id: item?.id || uid(`rank_${index + 1}`),
            label: item?.label || '',
        }));
        base.ranking = {
            mode: question?.ranking?.mode === 'exact' ? 'exact' : 'position',
            items: items.length ? items : [{ id: uid('rank'), label: 'Item 1' }, { id: uid('rank'), label: 'Item 2' }],
            correct_order: ensureArray(question?.ranking?.correct_order),
        };
        if (!base.ranking.correct_order.length) {
            base.ranking.correct_order = base.ranking.items.map((item) => item.id);
        }
    }

    if (type === 'matching') {
        base.matching = {
            pairs: ensureArray(question?.matching?.pairs).map((pair, index) => ({
                id: pair?.id || uid(`pair_${index + 1}`),
                left: pair?.left || '',
                right: pair?.right || '',
            })),
        };
        if (!base.matching.pairs.length) {
            base.matching.pairs = [{ id: uid('pair'), left: 'Term', right: 'Definition' }];
        }
    }

    if (type === 'matrix_true_false') {
        base.matrix = {
            statements: ensureArray(question?.matrix?.statements).map((statement, index) => ({
                id: statement?.id || uid(`stmt_${index + 1}`),
                text: statement?.text || '',
                correct: !!statement?.correct,
                points_correct: Number(statement?.points_correct ?? 1),
                points_incorrect: Number(statement?.points_incorrect ?? 0),
            })),
        };
        if (!base.matrix.statements.length) {
            base.matrix.statements = [{ id: uid('stmt'), text: 'Statement', correct: true, points_correct: 1, points_incorrect: 0 }];
        }
    }

    return base;
};

const buildBiasWarnings = (question) => {
    const warnings = [];
    const text = question?.text || '';
    if (LEADING_LANGUAGE.test(text)) {
        warnings.push('Leading phrasing detected in question text.');
    }

    const choices = ensureArray(question?.choices);
    if (choices.length) {
        if (choices.length < 2) {
            warnings.push('At least 2 options are recommended.');
        }
        const lengths = choices.map((choice) => (choice?.label || '').trim().length).filter(Boolean);
        if (lengths.length > 1) {
            const min = Math.min(...lengths);
            const max = Math.max(...lengths);
            if (min > 0 && max / min >= 3) {
                warnings.push('Option length imbalance detected (can cue answer choice).');
            }
        }
    }

    if (question?.type === 'multi_select') {
        const positives = choices.filter((choice) => Number(choice?.points || 0) > 0).length;
        const nonPositives = choices.length - positives;
        if (positives > 0 && nonPositives === 0) {
            warnings.push('Multi-select has no distractors/neutral options.');
        }
    }

    return warnings;
};

export default function QuestionEditor({ question, onChange, onRemove, onClone, stages = [] }) {
    const safeQuestion = normalizeQuestionForEditor(question || {});
    const availableStages = stages && stages.length > 0
        ? stages
        : ['Market & Offering', 'Business Model', 'Execution'];
    const warnings = buildBiasWarnings(safeQuestion);

    const updateQuestion = (key, value) => {
        onChange({ ...safeQuestion, [key]: value, version: 2 });
    };

    const updateChoices = (nextChoices) => updateQuestion('choices', nextChoices);

    const updateChoice = (index, patch) => {
        const next = [...(safeQuestion.choices || [])];
        next[index] = { ...next[index], ...patch };
        updateChoices(next);
    };

    const addChoice = () => {
        updateChoices([...(safeQuestion.choices || []), { id: uid('choice'), label: '', points: 0 }]);
    };

    const removeChoice = (index) => {
        updateChoices((safeQuestion.choices || []).filter((_, i) => i !== index));
    };

    const renderChoiceEditor = () => (
        <div className="form-control w-full md:col-span-2 p-4 bg-base-200 rounded-lg smc-question-options">
            <label className="label font-bold"><span className="label-text">Choices & Points</span></label>
            {(safeQuestion.choices || []).map((choice, index) => (
                <div key={choice.id || index} className="flex gap-2 mb-2 items-center">
                    <input
                        type="text"
                        className="input input-bordered input-sm flex-1"
                        value={choice.label || ''}
                        onChange={(e) => updateChoice(index, { label: e.target.value })}
                        placeholder="Option Text"
                    />
                    <input
                        type="number"
                        className="input input-bordered input-sm w-28"
                        value={choice.points ?? 0}
                        onChange={(e) => updateChoice(index, { points: Number(e.target.value || 0) })}
                    />
                    <button className="btn btn-square btn-sm btn-ghost text-error" onClick={() => removeChoice(index)}>
                        &times;
                    </button>
                </div>
            ))}
            <button className="btn btn-sm btn-secondary btn-outline" onClick={addChoice}>+ Add Choice</button>
        </div>
    );

    const renderNumericEditor = () => (
        <div className="form-control w-full md:col-span-2 p-4 bg-base-200 rounded-lg">
            <label className="label font-bold"><span className="label-text">Numeric Configuration</span></label>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-2">
                <input
                    type="number"
                    className="input input-bordered input-sm"
                    value={safeQuestion.numeric?.correct_value ?? 0}
                    onChange={(e) => updateQuestion('numeric', { ...safeQuestion.numeric, correct_value: Number(e.target.value || 0) })}
                    placeholder="Correct Value"
                />
                <input
                    type="number"
                    className="input input-bordered input-sm"
                    value={safeQuestion.numeric?.tolerance ?? 0}
                    onChange={(e) => updateQuestion('numeric', { ...safeQuestion.numeric, tolerance: Number(e.target.value || 0) })}
                    placeholder="Tolerance"
                />
                <input
                    type="text"
                    className="input input-bordered input-sm"
                    value={safeQuestion.numeric?.unit ?? ''}
                    onChange={(e) => updateQuestion('numeric', { ...safeQuestion.numeric, unit: e.target.value })}
                    placeholder="Unit (optional)"
                />
                <input
                    type="number"
                    className="input input-bordered input-sm"
                    value={safeQuestion.grading?.max_points ?? 1}
                    onChange={(e) => updateQuestion('grading', { ...safeQuestion.grading, max_points: Number(e.target.value || 1) })}
                    placeholder="Max Points"
                />
            </div>
        </div>
    );

    const renderRankingEditor = () => {
        const items = safeQuestion.ranking?.items || [];
        const order = safeQuestion.ranking?.correct_order || [];
        const addItem = () => {
            const nextItems = [...items, { id: uid('rank'), label: '' }];
            updateQuestion('ranking', { ...safeQuestion.ranking, items: nextItems, correct_order: nextItems.map((item) => item.id) });
        };
        return (
            <div className="form-control w-full md:col-span-2 p-4 bg-base-200 rounded-lg">
                <label className="label font-bold"><span className="label-text">Ranking Items (top to bottom is correct order)</span></label>
                {items.map((item, index) => (
                    <div key={item.id || index} className="flex gap-2 mb-2 items-center">
                        <span className="text-xs opacity-60 w-6">{index + 1}</span>
                        <input
                            type="text"
                            className="input input-bordered input-sm flex-1"
                            value={item.label || ''}
                            onChange={(e) => {
                                const next = [...items];
                                next[index] = { ...next[index], label: e.target.value };
                                updateQuestion('ranking', { ...safeQuestion.ranking, items: next, correct_order: order.length ? order : next.map((x) => x.id) });
                            }}
                        />
                    </div>
                ))}
                <button className="btn btn-sm btn-secondary btn-outline" onClick={addItem}>+ Add Ranking Item</button>
            </div>
        );
    };

    const renderMatchingEditor = () => {
        const pairs = safeQuestion.matching?.pairs || [];
        const addPair = () => updateQuestion('matching', { ...safeQuestion.matching, pairs: [...pairs, { id: uid('pair'), left: '', right: '' }] });
        return (
            <div className="form-control w-full md:col-span-2 p-4 bg-base-200 rounded-lg">
                <label className="label font-bold"><span className="label-text">Matching Pairs</span></label>
                {pairs.map((pair, index) => (
                    <div key={pair.id || index} className="grid grid-cols-2 gap-2 mb-2">
                        <input
                            type="text"
                            className="input input-bordered input-sm"
                            value={pair.left || ''}
                            onChange={(e) => {
                                const next = [...pairs];
                                next[index] = { ...next[index], left: e.target.value };
                                updateQuestion('matching', { ...safeQuestion.matching, pairs: next });
                            }}
                            placeholder="Left item"
                        />
                        <input
                            type="text"
                            className="input input-bordered input-sm"
                            value={pair.right || ''}
                            onChange={(e) => {
                                const next = [...pairs];
                                next[index] = { ...next[index], right: e.target.value };
                                updateQuestion('matching', { ...safeQuestion.matching, pairs: next });
                            }}
                            placeholder="Correct match"
                        />
                    </div>
                ))}
                <button className="btn btn-sm btn-secondary btn-outline" onClick={addPair}>+ Add Pair</button>
            </div>
        );
    };

    const renderMatrixEditor = () => {
        const statements = safeQuestion.matrix?.statements || [];
        const addStatement = () => updateQuestion('matrix', {
            ...safeQuestion.matrix,
            statements: [...statements, { id: uid('stmt'), text: '', correct: true, points_correct: 1, points_incorrect: 0 }]
        });
        return (
            <div className="form-control w-full md:col-span-2 p-4 bg-base-200 rounded-lg">
                <label className="label font-bold"><span className="label-text">True / False Statements</span></label>
                {statements.map((statement, index) => (
                    <div key={statement.id || index} className="grid grid-cols-12 gap-2 mb-2 items-center">
                        <input
                            type="text"
                            className="input input-bordered input-sm col-span-7"
                            value={statement.text || ''}
                            onChange={(e) => {
                                const next = [...statements];
                                next[index] = { ...next[index], text: e.target.value };
                                updateQuestion('matrix', { ...safeQuestion.matrix, statements: next });
                            }}
                            placeholder="Statement text"
                        />
                        <select
                            className="select select-bordered select-sm col-span-2"
                            value={statement.correct ? 'true' : 'false'}
                            onChange={(e) => {
                                const next = [...statements];
                                next[index] = { ...next[index], correct: e.target.value === 'true' };
                                updateQuestion('matrix', { ...safeQuestion.matrix, statements: next });
                            }}
                        >
                            <option value="true">True</option>
                            <option value="false">False</option>
                        </select>
                        <input
                            type="number"
                            className="input input-bordered input-sm col-span-2"
                            value={statement.points_correct ?? 1}
                            onChange={(e) => {
                                const next = [...statements];
                                next[index] = { ...next[index], points_correct: Number(e.target.value || 0) };
                                updateQuestion('matrix', { ...safeQuestion.matrix, statements: next });
                            }}
                            placeholder="Points"
                        />
                        <button
                            className="btn btn-square btn-sm btn-ghost text-error col-span-1"
                            onClick={() => updateQuestion('matrix', { ...safeQuestion.matrix, statements: statements.filter((_, i) => i !== index) })}
                        >
                            &times;
                        </button>
                    </div>
                ))}
                <button className="btn btn-sm btn-secondary btn-outline" onClick={addStatement}>+ Add Statement</button>
            </div>
        );
    };

    return (
        <div className="card bg-base-100 shadow-md border border-base-200 mb-4 w-full smc-question-card" style={{ maxWidth: '100%' }}>
            <div className="card-body p-4 smc-question-card-body">
                <div className="flex justify-between items-start mb-4 smc-question-card-top">
                    <h3 className="card-title text-sm uppercase tracking-wide opacity-70 truncate max-w-[80%] smc-question-card-title">
                        {safeQuestion.text || 'New Question'}
                    </h3>
                    <div className="smc-question-header-actions">
                        {typeof onClone === 'function' && (
                            <button className="btn btn-ghost btn-xs smc-question-action" onClick={onClone}>
                                <Copy size={13} />
                                Clone
                            </button>
                        )}
                        <button className="btn btn-ghost btn-xs text-error smc-question-action smc-question-action-delete" onClick={onRemove}>
                            <Trash2 size={13} />
                            Remove
                        </button>
                    </div>
                </div>

                {warnings.length > 0 && (
                    <div className="alert alert-warning mb-4">
                        <span className="text-xs">{warnings.join(' ')}</span>
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 smc-question-grid">
                    <div className="form-control w-full">
                        <label className="label"><span className="label-text">Stage</span></label>
                        <select
                            className="select select-bordered w-full"
                            value={safeQuestion.stage}
                            onChange={(e) => updateQuestion('stage', e.target.value)}
                        >
                            {!safeQuestion.stage && <option value="">Select Stage...</option>}
                            {availableStages.map((stage) => <option key={stage} value={stage}>{stage}</option>)}
                        </select>
                    </div>

                    <div className="form-control w-full">
                        <label className="label"><span className="label-text">Type</span></label>
                        <select
                            className="select select-bordered w-full"
                            value={safeQuestion.type}
                            onChange={(e) => {
                                const next = normalizeQuestionForEditor({ ...safeQuestion, type: e.target.value });
                                onChange({ ...next, version: 2 });
                            }}
                        >
                            {QUESTION_TYPES.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
                        </select>
                    </div>

                    <div className="form-control w-full md:col-span-2">
                        <label className="label"><span className="label-text">Short Label <span className="text-xs opacity-70 font-normal">(for Reports)</span></span></label>
                        <input
                            type="text"
                            className="input input-bordered w-full"
                            value={safeQuestion.indicator || ''}
                            onChange={(e) => updateQuestion('indicator', e.target.value)}
                            placeholder="e.g. Market Readiness"
                        />
                    </div>

                    <div className="form-control w-full md:col-span-2">
                        <label className="label"><span className="label-text">Question Text (User Facing)</span></label>
                        <input
                            type="text"
                            className="input input-bordered w-full"
                            value={safeQuestion.text || ''}
                            onChange={(e) => updateQuestion('text', e.target.value)}
                            placeholder="e.g. Select all statements that are true."
                        />
                    </div>

                    <div className="form-control w-full md:col-span-2">
                        <label className="label"><span className="label-text">Guidance</span></label>
                        <textarea
                            className="textarea textarea-bordered h-24 w-full"
                            value={safeQuestion.guidance || ''}
                            onChange={(e) => updateQuestion('guidance', e.target.value)}
                        />
                    </div>

                    {(safeQuestion.type === 'single_choice' || safeQuestion.type === 'multi_select') && renderChoiceEditor()}
                    {safeQuestion.type === 'numeric' && renderNumericEditor()}
                    {safeQuestion.type === 'ranking' && renderRankingEditor()}
                    {safeQuestion.type === 'matching' && renderMatchingEditor()}
                    {safeQuestion.type === 'matrix_true_false' && renderMatrixEditor()}
                </div>
            </div>
        </div>
    );
}

