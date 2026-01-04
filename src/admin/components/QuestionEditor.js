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
        type = 'scorable',
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
        { label: 'Scorable (Standard 4-point)', value: 'scorable' },
        { label: 'Text Input', value: 'text' },
        { label: 'Dropdown (Unscored)', value: 'select' },
        { label: 'Date', value: 'date' }
    ];

    const updateQuestion = (key, value) => {
        onChange({ ...question, [key]: value });
    };

    // Handle Option Changes for 'select' type
    const addOption = () => {
        // Simple string options for select
        updateQuestion('options', [...options, 'New Option']);
    };

    const updateOption = (index, value) => {
        const newOptions = [...options];
        newOptions[index] = value;
        updateQuestion('options', newOptions);
    };

    const removeOption = (index) => {
        const newOptions = options.filter((_, i) => i !== index);
        updateQuestion('options', newOptions);
    };

    // If not open, maybe show a summary? 
    // For now assuming this is used inside a list or modal.
    // Let's style it as a card to match the visual of the Vue app list item or form.

    return (
        <div className="card bg-base-100 shadow-md border border-base-200 mb-4 w-full" style={{ maxWidth: '100%' }}>
            <div className="card-body p-4">
                <div className="flex justify-between items-start mb-4">
                    <h3 className="card-title text-sm uppercase tracking-wide opacity-70">
                        {indicator || 'New Question'}
                    </h3>
                    <button className="btn btn-ghost btn-xs text-error" onClick={onRemove}>
                        Remove
                    </button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Stage */}
                    <div className="form-control w-full">
                        <label className="label"><span className="label-text">Stage</span></label>
                        <select
                            className="select select-bordered w-full"
                            value={stage}
                            onChange={(e) => updateQuestion('stage', e.target.value)}
                        >
                            {stages.map(s => <option key={s} value={s}>{s}</option>)}
                        </select>
                    </div>

                    {/* Type */}
                    <div className="form-control w-full">
                        <label className="label"><span className="label-text">Type</span></label>
                        <select
                            className="select select-bordered w-full"
                            value={type}
                            onChange={(e) => updateQuestion('type', e.target.value)}
                        >
                            {types.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                        </select>
                    </div>

                    {/* Indicator */}
                    <div className="form-control w-full md:col-span-2">
                        <label className="label"><span className="label-text">Indicator (Internal Ref)</span></label>
                        <input
                            type="text"
                            className="input input-bordered w-full"
                            value={indicator}
                            onChange={(e) => updateQuestion('indicator', e.target.value)}
                            placeholder="e.g. Market Readiness"
                        />
                    </div>

                    {/* Question Text */}
                    <div className="form-control w-full md:col-span-2">
                        <label className="label"><span className="label-text">Question Text (User Facing)</span></label>
                        <input
                            type="text"
                            className="input input-bordered w-full"
                            value={text}
                            onChange={(e) => updateQuestion('text', e.target.value)}
                            placeholder="e.g. How developed is the market?"
                        />
                    </div>

                    {/* Key Text */}
                    <div className="form-control w-full md:col-span-2">
                        <label className="label"><span className="label-text">Key Text / Example</span></label>
                        <input
                            type="text"
                            className="input input-bordered w-full"
                            value={key_text}
                            onChange={(e) => updateQuestion('key_text', e.target.value)}
                            placeholder="Example: No Ready Market"
                        />
                    </div>

                    {/* Guidance */}
                    <div className="form-control w-full md:col-span-2">
                        <label className="label"><span className="label-text">Guidance</span></label>
                        <textarea
                            className="textarea textarea-bordered h-24 w-full"
                            value={guidance}
                            onChange={(e) => updateQuestion('guidance', e.target.value)}
                        ></textarea>
                    </div>

                    {/* Scorable Info */}
                    {type === 'scorable' && (
                        <div className="alert alert-info shadow-sm md:col-span-2">
                            <span className="text-xs">
                                Scorable questions use the standard 4-point scale: <strong>Great (15), Good (10), Borderline (5), Flag (-5)</strong>.
                            </span>
                        </div>
                    )}

                    {/* Select Options */}
                    {type === 'select' && (
                        <div className="form-control w-full md:col-span-2 p-4 bg-base-200 rounded-lg">
                            <label className="label font-bold"><span className="label-text">Options</span></label>
                            {options.map((opt, index) => (
                                <div key={index} className="flex gap-2 mb-2">
                                    <input
                                        type="text"
                                        className="input input-bordered input-sm flex-1"
                                        value={opt}
                                        onChange={(e) => updateOption(index, e.target.value)}
                                    />
                                    <button className="btn btn-square btn-sm btn-ghost text-error" onClick={() => removeOption(index)}>
                                        &times;
                                    </button>
                                </div>
                            ))}
                            <button className="btn btn-sm btn-secondary btn-outline" onClick={addOption}>+ Add Option</button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
