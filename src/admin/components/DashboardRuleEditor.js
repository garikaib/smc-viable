import { __ } from '@wordpress/i18n';
import {
    TextControl,
    SelectControl,
    TextareaControl,
    Button,
    BaseControl
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { Trash2 } from 'lucide-react';

/**
 * Dashboard Rule Editor Component.
 * 
 * @param {Object} props Props.
 * @param {Object} props.rule Rule object.
 * @param {Function} props.onChange Callback to update rule.
 * @param {Function} props.onRemove Callback to remove rule.
 */
export default function DashboardRuleEditor({ rule, onChange, onRemove }) {
    const {
        condition_text = '',
        message = '',
        logic = { operator: 'gt', value: 0, min: 0, max: 0 },
        style = { color: 'green', icon: 'check' }
    } = rule;

    const operators = [
        { label: 'Greater Than (> )', value: 'gt' },
        { label: 'Less Than (< )', value: 'lt' },
        { label: 'Between (Range)', value: 'between' },
        { label: 'Greater or Equal (>=)', value: 'gte' },
        { label: 'Less or Equal (<=)', value: 'lte' },
        { label: 'Equal (=)', value: 'eq' },
    ];

    const colors = [
        { label: 'Green (Success)', value: 'green' },
        { label: 'Light Green (Good)', value: 'light-green' },
        { label: 'Orange (Warning)', value: 'orange' },
        { label: 'Red (Danger)', value: 'red' },
    ];

    // Helpers to update nested objects
    const updateLogic = (key, val) => {
        onChange({ ...rule, logic: { ...logic, [key]: val } });
    };

    const updateStyle = (key, val) => {
        onChange({ ...rule, style: { ...style, [key]: val } });
    };

    return (
        <div className="card bg-base-100 shadow-md border border-base-200 mb-4 w-full" style={{ maxWidth: '100%' }}>
            <div className="card-body p-4">
                <div className="flex justify-between items-center mb-4">
                    <h3 className="card-title text-sm font-bold uppercase tracking-wide opacity-70">
                        {condition_text || 'New Rule'}
                    </h3>
                    <button className="btn btn-ghost btn-xs text-error" onClick={onRemove}>
                        <Trash2 size={13} />
                        Remove
                    </button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {/* 1. Condition Title */}
                    <div className="form-control w-full md:col-span-2">
                        <label className="label"><span className="label-text">Rule Name (Title)</span></label>
                        <input
                            type="text"
                            className="input input-bordered w-full"
                            value={condition_text}
                            onChange={(e) => onChange({ ...rule, condition_text: e.target.value })}
                            placeholder="e.g. Excellent Score"
                        />
                    </div>

                    {/* 2. Logic Builder */}
                    <div className="form-control w-full p-4 bg-base-200 rounded-lg md:col-span-2">
                        <label className="label font-bold mb-2"><span className="label-text">Condition Logic</span></label>
                        <div className="flex flex-wrap md:flex-nowrap gap-4 items-end">
                            <div className="w-full md:w-1/3">
                                <label className="label text-xs">If Total Score is...</label>
                                <select
                                    className="select select-bordered w-full"
                                    value={logic.operator}
                                    onChange={(e) => updateLogic('operator', e.target.value)}
                                >
                                    {operators.map(op => <option key={op.value} value={op.value}>{op.label}</option>)}
                                </select>
                            </div>

                            {logic.operator === 'between' ? (
                                <>
                                    <div className="w-1/2 md:w-1/4">
                                        <label className="label text-xs">Min Value</label>
                                        <input
                                            type="number"
                                            className="input input-bordered w-full"
                                            value={logic.min}
                                            onChange={(e) => updateLogic('min', parseInt(e.target.value))}
                                        />
                                    </div>
                                    <div className="w-1/2 md:w-1/4">
                                        <label className="label text-xs">Max Value</label>
                                        <input
                                            type="number"
                                            className="input input-bordered w-full"
                                            value={logic.max}
                                            onChange={(e) => updateLogic('max', parseInt(e.target.value))}
                                        />
                                    </div>
                                </>
                            ) : (
                                <div className="w-full md:w-1/3">
                                    <label className="label text-xs">Target Value</label>
                                    <input
                                        type="number"
                                        className="input input-bordered w-full"
                                        value={logic.value}
                                        onChange={(e) => updateLogic('value', parseInt(e.target.value))}
                                    />
                                </div>
                            )}
                        </div>
                    </div>

                    {/* 3. Message & Style */}
                    <div className="form-control w-full md:col-span-2">
                        <label className="label"><span className="label-text">Feedback Message</span></label>
                        <textarea
                            className="textarea textarea-bordered h-24 w-full"
                            value={message}
                            onChange={(e) => onChange({ ...rule, message: e.target.value })}
                            placeholder="Message shown to user..."
                        ></textarea>
                    </div>

                    <div className="form-control w-full">
                        <label className="label"><span className="label-text">Color Theme</span></label>
                        <select
                            className="select select-bordered w-full"
                            value={style.color}
                            onChange={(e) => updateStyle('color', e.target.value)}
                        >
                            {colors.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </div>

                </div>
            </div>
        </div>
    );
}
