import { __ } from '@wordpress/i18n';

/**
 * ScoreIndicator Component.
 * 
 * Renders 4 buttons for scoring: Great (15), Good (10), Borderline (5), Flag (-5).
 * 
 * @param {Object} props
 * @param {number} props.value Current value
 * @param {Function} props.onChange Callback with new value
 */
export default function ScoreIndicator({ value, onChange }) {
    const bands = [
        { label: __('Great', 'smc-viable'), value: 15, class: 'btn-secondary text-white' },
        { label: __('Good', 'smc-viable'), value: 10, class: 'btn-info text-white' },
        { label: __('Borderline', 'smc-viable'), value: 5, class: 'btn-accent text-white' },
        { label: __('Flag', 'smc-viable'), value: -5, class: 'btn-primary text-white' }
    ];

    return (
        <div className="flex flex-wrap gap-2">
            {bands.map((band) => (
                <button
                    key={band.value}
                    className={`btn btn-sm ${band.class} ${value === band.value ? 'ring-2 ring-offset-2 ring-black' : 'opacity-60 hover:opacity-100'}`}
                    onClick={() => onChange(band.value)}
                >
                    {band.label}
                </button>
            ))}
        </div>
    );
}
