const normalizeType = (type) => {
    if (type === 'select' || type === 'scorable') return 'single_choice';
    if (type === 'text') return 'short_text';
    if (type === 'date') return 'date_month';
    return type || 'short_text';
};

const asBool = (value) => {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value === 1;
    if (typeof value === 'string') {
        const lower = value.toLowerCase().trim();
        if (['true', '1', 'yes'].includes(lower)) return true;
        if (['false', '0', 'no'].includes(lower)) return false;
    }
    return null;
};

const getChoices = (question) => {
    if (Array.isArray(question?.choices)) return question.choices;
    if (Array.isArray(question?.options)) {
        return question.options.map((option, index) => {
            if (typeof option === 'string') {
                return { id: `choice_${index + 1}`, label: option, points: 0 };
            }
            return {
                id: option?.id || `choice_${index + 1}`,
                label: option?.label || '',
                points: Number(option?.points ?? option?.score ?? 0),
            };
        });
    }
    return [];
};

const gradeQuestion = (question, response) => {
    const type = normalizeType(question?.type);
    const grading = question?.grading || {};

    if (grading?.mode === 'none') {
        return { score: 0, max: 0 };
    }

    if (type === 'single_choice') {
        const choices = getChoices(question);
        const max = Math.max(0, ...choices.map((choice) => Number(choice?.points || 0)));
        const selected = choices.find((choice) => `${choice?.id}` === `${response}` || `${choice?.label}` === `${response}`);
        return { score: Number(selected?.points || 0), max };
    }

    if (type === 'multi_select') {
        const choices = getChoices(question);
        const uncappedMax = choices.filter((choice) => Number(choice?.points || 0) > 0).reduce((sum, choice) => sum + Number(choice.points || 0), 0);
        const capRaw = question?.grading?.cap_points;
        const cap = Number(capRaw);
        const hasCap = capRaw !== '' && capRaw !== null && capRaw !== undefined && !Number.isNaN(cap) && cap >= 0;
        const max = hasCap ? cap : uncappedMax;
        const selected = Array.isArray(response) ? response.map(String) : [];
        let score = 0;
        choices.forEach((choice) => {
            const isSelected = selected.includes(`${choice?.id}`) || selected.includes(`${choice?.label}`);
            if (isSelected) score += Number(choice?.points || 0);
        });
        const min = Number(question?.grading?.min_points ?? 0);
        if (!Number.isNaN(min) && score < min) score = min;
        if (hasCap && score > cap) score = cap;
        return { score, max };
    }

    if (type === 'numeric') {
        const max = Number(question?.grading?.max_points ?? 1);
        const expected = Number(question?.numeric?.correct_value ?? 0);
        const tolerance = Math.abs(Number(question?.numeric?.tolerance ?? 0));
        const actual = Number(response);
        if (Number.isNaN(actual)) return { score: 0, max };
        return { score: Math.abs(actual - expected) <= tolerance ? max : 0, max };
    }

    if (type === 'ranking') {
        const max = Number(question?.grading?.max_points ?? 1);
        const correct = Array.isArray(question?.ranking?.correct_order) ? question.ranking.correct_order.map(String) : [];
        let order = [];
        if (Array.isArray(response)) {
            order = response.map(String);
        } else if (response && typeof response === 'object') {
            order = Object.entries(response)
                .filter(([, pos]) => !Number.isNaN(Number(pos)))
                .sort((a, b) => Number(a[1]) - Number(b[1]))
                .map(([id]) => String(id));
        }
        if (!order.length || !correct.length) return { score: 0, max };
        if ((question?.ranking?.mode || 'position') === 'exact') {
            return { score: JSON.stringify(order) === JSON.stringify(correct) ? max : 0, max };
        }
        const count = Math.min(order.length, correct.length);
        let correctPositions = 0;
        for (let i = 0; i < count; i += 1) {
            if (order[i] === correct[i]) correctPositions += 1;
        }
        return { score: count > 0 ? max * (correctPositions / count) : 0, max };
    }

    if (type === 'matching') {
        const pairs = Array.isArray(question?.matching?.pairs) ? question.matching.pairs : [];
        const max = Number(question?.grading?.max_points ?? (pairs.length || 1));
        if (!response || typeof response !== 'object') return { score: 0, max };
        let correct = 0;
        pairs.forEach((pair) => {
            if (`${response[pair.id] || ''}` === `${pair.right || ''}`) correct += 1;
        });
        return { score: pairs.length ? max * (correct / pairs.length) : 0, max };
    }

    if (type === 'matrix_true_false') {
        const statements = Array.isArray(question?.matrix?.statements) ? question.matrix.statements : [];
        if (!response || typeof response !== 'object') return { score: 0, max: statements.reduce((sum, s) => sum + Math.max(0, Number(s?.points_correct ?? 1)), 0) };
        let max = 0;
        let score = 0;
        statements.forEach((statement) => {
            const pointsCorrect = Number(statement?.points_correct ?? 1);
            const pointsIncorrect = Number(statement?.points_incorrect ?? 0);
            max += Math.max(0, pointsCorrect);
            const answer = asBool(response[statement.id]);
            if (answer === null) return;
            score += answer === !!statement.correct ? pointsCorrect : pointsIncorrect;
        });
        return { score, max };
    }

    return { score: 0, max: 0 };
};

export const computeQuizScores = (quiz, answers) => {
    const questions = quiz?.meta?._smc_quiz_questions || [];
    const scoreByStage = {};
    const questionScores = {};
    let total = 0;
    let max = 0;

    questions.forEach((question) => {
        const stage = question?.stage || 'Other';
        if (!scoreByStage[stage]) {
            scoreByStage[stage] = { total: 0, max: 0, flags: 0, items: [] };
        }
        const key = question?.id;
        const { score, max: questionMax } = gradeQuestion(question, answers?.[key]);
        total += score;
        max += questionMax;
        scoreByStage[stage].total += score;
        scoreByStage[stage].max += questionMax;
        if (score < 0) scoreByStage[stage].flags += 1;
        questionScores[key] = { score, max: questionMax };
        scoreByStage[stage].items.push({ id: key, label: question?.indicator || question?.text || key, score, max: questionMax });
    });

    const flaggedItems = Object.entries(scoreByStage)
        .filter(([, stage]) => {
            const pct = Math.round((Number(stage.total || 0) / Math.max(1, Number(stage.max || 0))) * 100);
            return pct < 40;
        })
        .map(([stageName, stage]) => ({ stage: stageName, score: Math.round((Number(stage.total || 0) / Math.max(1, Number(stage.max || 0))) * 100), message: 'Stage performance is below threshold.' }));

    return {
        totalScore: Math.round(total),
        maxPossibleScore: Math.round(Math.max(0, max)),
        scoresByStage: scoreByStage,
        flaggedItems,
        questionScores,
        scoreData: {
            total_score: Math.round(total),
            max_score: Math.round(Math.max(0, max)),
            scores_by_stage: scoreByStage,
            answers: answers || {},
            question_scores: questionScores,
        }
    };
};
