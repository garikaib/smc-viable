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
export default function QuestionEditor({ question, onChange, onRemove }) {
    const { type, text, options = [], score = 0 } = question;

    const updateQuestion = (key, value) => {
        onChange({ ...question, [key]: value });
    };

    // Handle Option Changes for Dropdown
    const addOption = () => {
        const newOption = { label: '', score: 0 };
        updateQuestion('options', [...options, newOption]);
    };

    const updateOption = (index, key, value) => {
        const newOptions = [...options];
        newOptions[index] = { ...newOptions[index], [key]: value };
        updateQuestion('options', newOptions);
    };

    const removeOption = (index) => {
        const newOptions = options.filter((_, i) => i !== index);
        updateQuestion('options', newOptions);
    };

    return (
        <div className="smc-question-editor" style={{ border: '1px solid #ccc', padding: '15px', marginBottom: '15px', background: '#fff' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                <h3>{__('Question', 'smc-viable')}</h3>
                <Button isDestructive isSmall onClick={onRemove}>{__('Remove Question', 'smc-viable')}</Button>
            </div>

            <TextControl
                label={__('Question Text', 'smc-viable')}
                value={text}
                onChange={(val) => updateQuestion('text', val)}
                placeholder={__('Enter your question...', 'smc-viable')}
            />

            <SelectControl
                label={__('Question Type', 'smc-viable')}
                value={type}
                options={[
                    { label: 'Dropdown (Scored)', value: 'dropdown' },
                    { label: 'Open Ended (Unscored)', value: 'text' },
                ]}
                onChange={(val) => updateQuestion('type', val)}
            />

            {type === 'dropdown' && (
                <div className="smc-question-options" style={{ marginTop: '10px', paddingLeft: '10px', borderLeft: '3px solid #007cba' }}>
                    <h4>{__('Dropdown Options', 'smc-viable')}</h4>
                    {options.map((opt, index) => (
                        <div key={index} style={{ display: 'flex', gap: '10px', alignItems: 'flex-end', marginBottom: '10px' }}>
                            <div style={{ flex: 2 }}>
                                <TextControl
                                    label={index === 0 ? __('Option Label', 'smc-viable') : ''}
                                    value={opt.label}
                                    onChange={(val) => updateOption(index, 'label', val)}
                                />
                            </div>
                            <div style={{ flex: 1 }}>
                                <TextControl
                                    label={index === 0 ? __('Score', 'smc-viable') : ''}
                                    type="number"
                                    value={opt.score}
                                    onChange={(val) => updateOption(index, 'score', parseInt(val) || 0)}
                                />
                            </div>
                            <div>
                                <Button isDestructive isSmall icon="trash" onClick={() => removeOption(index)} aria-label="Remove Option" />
                            </div>
                        </div>
                    ))}
                    <Button isSecondary onClick={addOption}>{__('Add Option', 'smc-viable')}</Button>
                </div>
            )}

            {type === 'text' && (
                <p style={{ fontStyle: 'italic', color: '#666' }}>
                    {__('Open-ended questions allow text input from the user but do not contribute to the score.', 'smc-viable')}
                </p>
            )}
        </div>
    );
}
