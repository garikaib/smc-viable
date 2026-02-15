import { useEffect, useMemo, useState } from '@wordpress/element';
import { Save, UserCircle2 } from 'lucide-react';
import AnimatedLoader from '../components/AnimatedLoader';

const EMPTY_SOCIAL = {
    website: '',
    linkedin: '',
    twitter: '',
    facebook: '',
    instagram: '',
    youtube: '',
    tiktok: '',
};

export default function InstructorProfileBuilder() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');
    const [form, setForm] = useState({
        avatar: '',
        avatar_id: 0,
        intro: '',
        bio: '',
        experience: '',
        skillsText: '',
        social_links: { ...EMPTY_SOCIAL },
    });

    useEffect(() => {
        const loadProfile = async () => {
            try {
                const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/profile`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce },
                });
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload?.message || 'Could not load profile.');
                }

                const skills = Array.isArray(payload?.skills) ? payload.skills : [];
                setForm({
                    avatar: payload?.avatar || '',
                    avatar_id: Number(payload?.avatar_id || 0),
                    intro: payload?.intro || '',
                    bio: payload?.bio || '',
                    experience: payload?.experience || '',
                    skillsText: skills.join(', '),
                    social_links: {
                        ...EMPTY_SOCIAL,
                        ...(payload?.social_links || {}),
                    },
                });
            } catch (err) {
                setError(err?.message || 'Could not load profile.');
            } finally {
                setLoading(false);
            }
        };

        loadProfile();
    }, []);

    const skillsPreview = useMemo(() => {
        return String(form.skillsText || '')
            .split(/[\n,]+/)
            .map((item) => item.trim())
            .filter(Boolean);
    }, [form.skillsText]);

    const updateSocial = (key, value) => {
        setForm((prev) => ({
            ...prev,
            social_links: {
                ...prev.social_links,
                [key]: value,
            },
        }));
    };

    const handleSelectAvatar = () => {
        if (!window?.wp?.media) {
            setError('Media manager is unavailable.');
            return;
        }

        const frame = window.wp.media({
            title: 'Select profile photo',
            library: { type: 'image' },
            button: { text: 'Use this image' },
            multiple: false,
        });

        frame.on('select', () => {
            const attachment = frame.state().get('selection').first()?.toJSON();
            if (!attachment) {
                return;
            }

            const imageUrl =
                attachment?.sizes?.medium?.url ||
                attachment?.sizes?.thumbnail?.url ||
                attachment?.url ||
                '';

            setForm((prev) => ({
                ...prev,
                avatar: imageUrl,
                avatar_id: Number(attachment?.id || 0),
            }));
        });

        frame.open();
    };

    const handleRemoveAvatar = () => {
        setForm((prev) => ({
            ...prev,
            avatar: '',
            avatar_id: 0,
        }));
    };

    const handleSave = async (event) => {
        event.preventDefault();
        setSaving(true);
        setNotice('');
        setError('');

        const skills = String(form.skillsText || '')
            .split(/[\n,]+/)
            .map((item) => item.trim())
            .filter(Boolean);

        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/profile`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce,
                },
                body: JSON.stringify({
                    avatar: form.avatar,
                    avatar_id: Number(form.avatar_id || 0),
                    intro: form.intro,
                    bio: form.bio,
                    experience: form.experience,
                    skills,
                    social_links: form.social_links,
                }),
            });
            const payload = await response.json();
            if (!response.ok || payload?.success === false) {
                throw new Error(payload?.message || 'Could not save profile.');
            }
            setNotice(payload?.message || 'Profile saved.');
        } catch (err) {
            setError(err?.message || 'Could not save profile.');
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return <AnimatedLoader message="Loading instructor profile..." compact />;
    }

    return (
        <div className="smc-profile-builder max-w-5xl mx-auto">
            <div className="smc-dashboard-intro mb-10">
                <span className="inline-block px-3 py-1 rounded-full bg-teal-500/10 text-teal-600 text-xs font-bold tracking-widest mb-4 border border-teal-500/20">
                    INSTRUCTOR PROFILE
                </span>
                <h2 className="text-4xl font-black text-base-content tracking-tight mb-4 flex items-center gap-3">
                    <UserCircle2 size={34} /> Build Your Public Instructor Profile
                </h2>
                <p className="text-base-content/60 text-lg max-w-3xl leading-relaxed">
                    Add your intro, bio, experience, skills, and social links. Students can open this profile directly from the course player.
                </p>
            </div>

            <form onSubmit={handleSave} className="smc-glass-card p-8 rounded-3xl border border-base-content/10 space-y-6">
                <div className="grid grid-cols-1 gap-5">
                    <div className="smc-form-label">
                        Profile photo
                        <div className="flex items-center gap-4 mt-2 flex-wrap">
                            {form.avatar ? (
                                <img src={form.avatar} alt="Instructor profile" className="w-20 h-20 rounded-full object-cover border border-base-content/15" />
                            ) : (
                                <div className="w-20 h-20 rounded-full border border-dashed border-base-content/25 flex items-center justify-center text-base-content/45 text-xs">
                                    No photo
                                </div>
                            )}
                            <div className="flex items-center gap-2 flex-wrap">
                                <button type="button" className="smc-btn-primary" onClick={handleSelectAvatar}>
                                    {form.avatar ? 'Change Photo' : 'Upload Photo'}
                                </button>
                                {form.avatar && (
                                    <button
                                        type="button"
                                        className="px-4 py-2 rounded-xl border border-base-content/15 text-sm font-semibold text-base-content/80 hover:bg-base-content/5 transition"
                                        onClick={handleRemoveAvatar}
                                    >
                                        Remove
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    <label className="smc-form-label">
                        Intro
                        <input
                            type="text"
                            className="smc-form-input"
                            placeholder="Business strategist helping founders scale with clarity."
                            value={form.intro}
                            onChange={(e) => setForm((prev) => ({ ...prev, intro: e.target.value }))}
                        />
                    </label>

                    <label className="smc-form-label">
                        Bio
                        <textarea
                            className="smc-form-input smc-form-textarea"
                            rows={5}
                            value={form.bio}
                            onChange={(e) => setForm((prev) => ({ ...prev, bio: e.target.value }))}
                        />
                    </label>

                    <label className="smc-form-label">
                        Experience
                        <textarea
                            className="smc-form-input smc-form-textarea"
                            rows={5}
                            value={form.experience}
                            onChange={(e) => setForm((prev) => ({ ...prev, experience: e.target.value }))}
                        />
                    </label>

                    <label className="smc-form-label">
                        Skills (comma or line separated)
                        <textarea
                            className="smc-form-input smc-form-textarea"
                            rows={3}
                            placeholder="Brand strategy, Product marketing, Sales systems"
                            value={form.skillsText}
                            onChange={(e) => setForm((prev) => ({ ...prev, skillsText: e.target.value }))}
                        />
                    </label>
                    {skillsPreview.length > 0 && (
                        <div className="flex flex-wrap gap-2 -mt-2">
                            {skillsPreview.map((skill) => (
                                <span key={skill} className="px-3 py-1 rounded-full text-xs font-bold bg-teal-500/10 text-teal-600 border border-teal-500/20">
                                    {skill}
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {Object.keys(EMPTY_SOCIAL).map((network) => (
                        <label key={network} className="smc-form-label">
                            {network.charAt(0).toUpperCase() + network.slice(1)}
                            <input
                                type="url"
                                className="smc-form-input"
                                placeholder={`https://${network}.com/yourprofile`}
                                value={form.social_links[network] || ''}
                                onChange={(e) => updateSocial(network, e.target.value)}
                            />
                        </label>
                    ))}
                </div>

                {error && <p className="text-sm font-semibold text-red-500">{error}</p>}
                {notice && <p className="text-sm font-semibold text-teal-600">{notice}</p>}

                <div className="pt-2">
                    <button type="submit" className="smc-btn-primary" disabled={saving}>
                        <Save size={16} />
                        {saving ? 'Saving...' : 'Save Instructor Profile'}
                    </button>
                </div>
            </form>
        </div>
    );
}
