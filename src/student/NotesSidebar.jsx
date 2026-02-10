import { useState, useEffect } from '@wordpress/element';

export default function NotesSidebar({ lessonId }) {
    const [note, setNote] = useState('');
    const [status, setStatus] = useState(''); // 'saving', 'saved', 'error'

    // Mock Load Note
    useEffect(() => {
        // TODO: Fetch note for lessonId from API
        // For now, load from localStorage to simulate persistence
        const saved = localStorage.getItem(`smc_note_${lessonId}`);
        setNote(saved || '');
    }, [lessonId]);

    const handleChange = (e) => {
        setNote(e.target.value);
        setStatus('saving');
    };

    // Debounce Save
    useEffect(() => {
        if (status !== 'saving') return;

        const timeout = setTimeout(() => {
            // TODO: Save to API
            localStorage.setItem(`smc_note_${lessonId}`, note);
            setStatus('saved');
        }, 1000);

        return () => clearTimeout(timeout);
    }, [note, lessonId, status]);

    return (
        <div className="smc-notes-sidebar">
            <h3>My Sticky Notes</h3>
            <textarea
                placeholder="Type your notes here..."
                value={note}
                onChange={handleChange}
            ></textarea>
            <div className="smc-notes-status">
                {status === 'saving' && 'Saving...'}
                {status === 'saved' && 'Saved'}
            </div>
        </div>
    );
}
