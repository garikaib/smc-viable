import { __ } from '@wordpress/i18n';
import {
    TextControl,
    SelectControl,
    Button,
    PanelBody,
    PanelRow,
    BaseControl
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

/**
 * Question Editor Component.
 * 
 * @param {Object} props Props.
 * @param {Object} props.question Question object.
 * @param {Function} props.onChange Callback to update question.
 * @param {Function} props.onRemove Callback to remove question.
 */
export default function QuestionEditor({ question, onChange, onRemove, isOpen, onClose }) {
    const {
        type = 'select',
        text = '',
        stage = 'Market & Offering',
        indicator = '',
        key_text = '',
        guidance = '',
        options = []
    } = question;

    const stages = [
        "Foundation & Legal",
        "Market & Offering",
        "Operational Strategy & Capability",
        "Financial Health & Economics",
        "Investment & Future Readiness"
    ];

    const types = [
        { label: 'Dropdown (Scorable)', value: 'select' },
        { label: 'Text Input', value: 'text' },
        { label: 'Scorable (Leagcy 4-point)', value: 'scorable' },
        { label: 'Date', value: 'date' }
    ];

    const updateQuestion = (key, value) => {
        onChange({ ...question, [key]: value });
    };

    // ...

    // (Inside JSX)
    onChange = {(e) => updateQuestion('key_text', e.target.value)
}
placeholder = "Example: No Ready Market"
    />
    </div >

    {/* Guidance */ }
    < div className = "form-control w-full md:col-span-2" >
        <label className="label"><span className="label-text">Guidance</span></label>
        <textarea
            className="textarea textarea-bordered h-24 w-full"
            value={guidance}
            onChange={(e) => updateQuestion('guidance', e.target.value)}
        ></textarea>
    </div >

    {/* Scorable Info */ }
{
    type === 'scorable' && (
        <div className="alert alert-info shadow-sm md:col-span-2">
            <span className="text-xs">
                Scorable questions use the standard 4-point scale: <strong>Great (15), Good (10), Borderline (5), Flag (-5)</strong>.
            </span>
        </div>
    )
}

{/* Select Options */ }
{
    type === 'select' && (
        <div className="form-control w-full md:col-span-2 p-4 bg-base-200 rounded-lg">
            <label className="label font-bold"><span className="label-text">Options & Scores</span></label>
            <p className="text-xs mb-3 text-base-content/70">
                Assign scores: Great (15), Good (10), Borderline (5), Flag (-5). Leave 0/empty for neutral.
            </p>
            {/* Short Label (Indicator) */}
            <div className="form-control w-full md:col-span-2">
                <label className="label">
                    <span className="label-text">Short Label <span className="text-xs opacity-70 font-normal">(for Reports)</span></span>
                </label>
                <input
                    type="text"
                    className="input input-bordered w-full"
                    value={indicator}
                    onChange={(e) => updateQuestion('indicator', e.target.value)}
                    placeholder="e.g. Market Readiness"
                />
            </div>
            {options.map((opt, index) => {
                // Hande both string (legacy) and object (new) options
                const label = typeof opt === 'object' ? opt.label : opt;
                const score = typeof opt === 'object' ? opt.score : 0;

                return (
                    <div key={index} className="flex gap-2 mb-2 items-center">
                        <input
                            type="text"
                            className="input input-bordered input-sm flex-1"
                            value={label}
                            onChange={(e) => {
                                const newVal = typeof opt === 'object'
                                    ? { ...opt, label: e.target.value }
                                    : { label: e.target.value, score: 0 };
                                updateOption(index, newVal);
                            }}
                            placeholder="Option Text"
                        />
                        <select
                            className="select select-bordered select-sm w-32"
                            value={score}
                            onChange={(e) => {
                                const newVal = typeof opt === 'object'
                                    ? { ...opt, score: parseInt(e.target.value) }
                                    : { label: opt, score: parseInt(e.target.value) };
                                updateOption(index, newVal);
                            }}
                        >
                            <option value="0">None (0)</option>
                            <option value="15">Great (15)</option>
                            <option value="10">Good (10)</option>
                            <option value="5">Borderline (5)</option>
                            <option value="-5">Flag (-5)</option>
                        </select>
                        <button className="btn btn-square btn-sm btn-ghost text-error" onClick={() => removeOption(index)}>
                            &times;
                        </button>
                    </div>
                );
            })}
            <button className="btn btn-sm btn-secondary btn-outline" onClick={() => addOption()}>+ Add Option</button>
        </div>
    )
}
                </div >
            </div >
        </div >
    );
}
