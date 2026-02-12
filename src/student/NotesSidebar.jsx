import { useState, useEffect, useRef } from '@wordpress/element';
import { CheckCircle, Loader2, AlertCircle } from 'lucide-react';

export default function NotesSidebar({ lessonId, className = '' }) {
    const [note, setNote] = useState('');
    const [status, setStatus] = useState('idle'); // 'idle', 'loading', 'saving', 'saved', 'error'
    const [lastSaved, setLastSaved] = useState(null);
    const timeoutRef = useRef(null);

    // Fetch Note
    useEffect(() => {
        if (!lessonId) return;

        const fetchNote = async () => {
            setStatus('loading');
            try {
                const res = await fetch(`${wpApiSettings.root}smc/v1/notes?lesson_id=${lessonId}`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                if (res.ok) {
                    const data = await res.json();
                    setNote(data.content || '');
                    if (data.updated_at) {
                        setLastSaved(data.updated_at);
                    }
                    setStatus('idle');
                } else {
                    setStatus('error');
                }
            } catch (err) {
                console.error("Failed to load note", err);
                setStatus('error');
            }
        };

        fetchNote();
    }, [lessonId]);

    const handleChange = (e) => {
        const newValue = e.target.value;
        setNote(newValue);
        setStatus('typing');

        if (timeoutRef.current) clearTimeout(timeoutRef.current);

        timeoutRef.current = setTimeout(async () => {
            setStatus('saving');
            try {
                const res = await fetch(`${wpApiSettings.root}smc/v1/notes`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings.nonce
                    },
                    body: JSON.stringify({
                        lesson_id: lessonId,
                        content: newValue
                    })
                });

                if (res.ok) {
                    const data = await res.json();
                    setStatus('saved');
                    setLastSaved(new Date().toISOString());
                    // Reset to idle after 2s
                    setTimeout(() => setStatus(s => s === 'saved' ? 'idle' : s), 2000);
                } else {
                    setStatus('error');
                }
            } catch (err) {
                console.error("Failed to save note", err);
                setStatus('error');
            }
        }, 1500); // 1.5s debounce
    };

    return (
        <div className={`smc-notes-sidebar ${className}`.trim()}>
            <div className="p-6 pb-2 border-b border-base-content/5 flex items-center justify-between">
                <h3>My Sticky Notes</h3>
                <div className="text-[10px] opacity-60 flex items-center gap-1">
                    {status === 'loading' && <Loader2 size={12} className="animate-spin" />}
                    {status === 'saving' && <span className="flex items-center gap-1"><Loader2 size={12} className="animate-spin" /> Saving...</span>}
                    {status === 'saved' && <span className="text-emerald-500 flex items-center gap-1"><CheckCircle size={12} /> Saved</span>}
                    {status === 'error' && <span className="text-red-500 flex items-center gap-1"><AlertCircle size={12} /> Error</span>}
                </div>
            </div>
            <textarea
                placeholder="Capture your insights while you learn..."
                value={note}
                onChange={handleChange}
                spellCheck="false"
            ></textarea>
            {lastSaved && (
                <div className="smc-notes-status flex justify-between items-center">
                    <span>Last synced</span>
                    <span className="opacity-70">{new Date(lastSaved).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                </div>
            )}
        </div>
    );
}
