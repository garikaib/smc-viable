import { useState, useEffect } from '@wordpress/element';
import { ArrowLeft, Plus, GripVertical, Trash2, Edit, X, Search, ChevronUp, ChevronDown, ExternalLink } from 'lucide-react';

export default function CourseStructureEditor({ course, onBack }) {
    const [sections, setSections] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    // Lesson Search State
    const [isSearching, setIsSearching] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [activeSectionIndex, setActiveSectionIndex] = useState(null);

    useEffect(() => {
        const fetchStructure = async () => {
            try {
                const response = await fetch(`${wpApiSettings.root}smc/v1/courses/${course.id}/structure`, {
                    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
                });
                const data = await response.json();
                setSections(data.sections || []);
            } catch (err) {
                console.error("Failed to fetch structure", err);
            } finally {
                setLoading(false);
            }
        };

        fetchStructure();
    }, [course.id]);

    const handleSave = async () => {
        setSaving(true);
        try {
            // Transform sections back to ID-only lessons for storage
            const dataToSave = sections.map(s => ({
                title: s.title,
                lessons: s.lessons.map(l => l.id)
            }));

            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/courses/${course.id}/structure`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({ sections: dataToSave })
            });

            if (response.ok) {
                alert('Saved successfully!');
            }
        } catch (err) {
            console.error("Failed to save structure", err);
        } finally {
            setSaving(false);
        }
    };

    const addSection = () => {
        setSections([...sections, { title: 'New Section', lessons: [] }]);
    };

    const removeSection = (index) => {
        if (confirm('Are you sure you want to delete this section?')) {
            const newSections = [...sections];
            newSections.splice(index, 1);
            setSections(newSections);
        }
    };

    const openSearch = (sectionIndex) => {
        setActiveSectionIndex(sectionIndex);
        setSearchQuery('');
        setSearchResults([]);
        setIsSearching(true);
    };

    const searchLessons = async (query) => {
        setSearchQuery(query);
        if (query.length < 2) return;

        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/lessons/search?q=${encodeURIComponent(query)}`, {
                headers: { 'X-WP-Nonce': wpApiSettings.nonce }
            });
            const data = await response.json();
            setSearchResults(data);
        } catch (err) {
            console.error("Search failed", err);
        }
    };

    const attachLesson = (lesson) => {
        const newSections = [...sections];
        // Check if already in section? (Optional, but good UX)
        // newSections[activeSectionIndex].lessons.push(lesson);

        // For visual consistency, ensure we have the properties needed
        newSections[activeSectionIndex].lessons.push({
            id: lesson.id,
            title: lesson.title,
            type: lesson.type
        });

        setSections(newSections);
        setIsSearching(false);
    };

    const createNewLesson = async () => {
        const title = prompt("Enter new lesson title:");
        if (!title) return;

        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/lessons`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({ title, type: 'video' }) // Default type
            });

            if (response.ok) {
                const newLesson = await response.json();
                attachLesson(newLesson);
            }
        } catch (err) {
            console.error("Failed to create lesson", err);
        }
    };

    const removeLesson = (sectionIndex, lessonIndex) => {
        const newSections = [...sections];
        newSections[sectionIndex].lessons.splice(lessonIndex, 1);
        setSections(newSections);
    };

    const moveLesson = (sectionIndex, lessonIndex, direction) => {
        const newSections = [...sections];
        const lessons = newSections[sectionIndex].lessons;

        if (direction === 'up' && lessonIndex > 0) {
            [lessons[lessonIndex], lessons[lessonIndex - 1]] = [lessons[lessonIndex - 1], lessons[lessonIndex]];
        } else if (direction === 'down' && lessonIndex < lessons.length - 1) {
            [lessons[lessonIndex], lessons[lessonIndex + 1]] = [lessons[lessonIndex + 1], lessons[lessonIndex]];
        }

        setSections(newSections);
    };

    if (loading) return <div>Loading structure...</div>;

    return (
        <div className="smc-structure-editor relative">
            <header className="smc-editor-header flex justify-between items-center mb-6">
                <div className="flex items-center gap-4">
                    <button onClick={onBack} className="smc-btn-secondary">
                        <ArrowLeft size={18} className="mr-2" /> Back
                    </button>
                    <h2 className="text-xl font-bold text-base-content">Editing: {course.title}</h2>
                </div>
                <button
                    className="smc-btn-primary"
                    onClick={handleSave}
                    disabled={saving}
                >
                    <Save size={18} className="mr-2" />
                    {saving ? 'Saving...' : 'Save Changes'}
                </button>
            </header>

            <div className="smc-sections-container space-y-6">
                {sections.map((section, sIndex) => (
                    <div key={sIndex} className="bg-base-200 rounded-lg p-4 border border-base-content/10">
                        <div className="flex justify-between items-center mb-4">
                            <div className="flex items-center gap-3 flex-1">
                                <GripVertical className="text-base-content/30 cursor-move" size={20} />
                                <input
                                    type="text"
                                    className="bg-transparent text-lg font-bold text-base-content border-b border-transparent focus:border-primary focus:outline-none w-full"
                                    value={section.title}
                                    onChange={(e) => {
                                        const newSections = [...sections];
                                        newSections[sIndex].title = e.target.value;
                                        setSections(newSections);
                                    }}
                                />
                            </div>
                            <button
                                className="text-red-400 hover:text-red-300 ml-4"
                                title="Delete Section"
                                onClick={() => removeSection(sIndex)}
                            >
                                <Trash2 size={18} />
                            </button>
                        </div>

                        <div className="space-y-2 pl-4 border-l-2 border-base-content/10 ml-2">
                            {section.lessons.map((lesson, lIndex) => (
                                <div key={lesson.id} className="bg-base-300 p-3 rounded flex justify-between items-center group">
                                    <div className="flex items-center gap-3">
                                        <div className="flex flex-col">
                                            <button onClick={() => moveLesson(sIndex, lIndex, 'up')} className="text-base-content/30 hover:text-base-content disabled:opacity-30" disabled={lIndex === 0}>
                                                <ChevronUp size={12} />
                                            </button>
                                            <button onClick={() => moveLesson(sIndex, lIndex, 'down')} className="text-base-content/30 hover:text-base-content disabled:opacity-30" disabled={lIndex === section.lessons.length - 1}>
                                                <ChevronDown size={12} />
                                            </button>
                                        </div>
                                        <span className="text-base-content/40 text-sm w-6">
                                            {lesson.type === 'video' ? '🎥' : '📄'}
                                        </span>
                                        <span className="text-base-content font-medium">{lesson.title}</span>
                                    </div>

                                    <div className="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a
                                            href={`/wp-admin/post.php?post=${lesson.id}&action=edit`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="p-1 px-2 bg-blue-900 text-blue-200 rounded text-xs flex items-center hover:bg-blue-800"
                                            title="Edit Content in Gutenberg"
                                        >
                                            <Edit size={12} className="mr-1" /> Edit
                                        </a>
                                        <button
                                            className="text-red-400 hover:text-red-300 p-1"
                                            title="Remove Lesson"
                                            onClick={() => removeLesson(sIndex, lIndex)}
                                        >
                                            <Trash2 size={14} />
                                        </button>
                                    </div>
                                </div>
                            ))}

                            <button
                                className="w-full py-2 border border-dashed border-base-content/20 text-base-content/40 rounded hover:border-base-content/40 hover:text-base-content transition-colors mt-2"
                                onClick={() => openSearch(sIndex)}
                            >
                                <Plus size={16} className="inline mr-1" /> Add Lesson
                            </button>
                        </div>
                    </div>
                ))}

                <button
                    className="w-full py-4 bg-base-200 rounded-lg text-base-content/40 hover:bg-base-300 hover:text-base-content transition-colors font-bold border border-dashed border-base-content/20"
                    onClick={addSection}
                >
                    <Plus size={20} className="inline mr-2" /> Add New Section
                </button>
            </div>

            {/* Lesson Search Modal */}
            {isSearching && (
                <div className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
                    <div className="bg-base-100 w-full max-w-lg rounded-2xl border border-base-content/10 shadow-2xl overflow-hidden backdrop-blur-xl">
                        <div className="p-6 border-b border-base-content/5 flex justify-between items-center bg-base-100/50">
                            <h3 className="font-bold text-lg text-base-content">Add Lesson to Section</h3>
                            <button onClick={() => setIsSearching(false)} className="text-base-content/40 hover:text-base-content">
                                <X size={20} />
                            </button>
                        </div>

                        <div className="p-4">
                            <div className="relative mb-4">
                                <Search className="absolute left-3 top-3 text-base-content/30" size={18} />
                                <input
                                    type="text"
                                    className="w-full bg-base-200 border border-base-content/10 rounded-lg py-2.5 pl-10 pr-4 text-base-content focus:outline-none focus:border-teal-500 transition-all font-medium"
                                    placeholder="Search existing lessons..."
                                    value={searchQuery}
                                    onChange={(e) => searchLessons(e.target.value)}
                                    autoFocus
                                />
                            </div>

                            <div className="max-h-60 overflow-y-auto space-y-2 mb-4">
                                {searchResults.map(result => (
                                    <div
                                        key={result.id}
                                        className="p-3 bg-base-200 hover:bg-teal-500/10 hover:text-teal-500 cursor-pointer rounded-lg flex justify-between items-center transition-all"
                                        onClick={() => attachLesson(result)}
                                    >
                                        <span className="font-medium">{result.title}</span>
                                        <span className="text-xs bg-base-content/10 px-2 py-1 rounded text-base-content/60">{result.type}</span>
                                    </div>
                                ))}
                                {searchQuery.length > 2 && searchResults.length === 0 && (
                                    <div className="text-gray-500 text-center py-4">No lessons found.</div>
                                )}
                            </div>

                            <div className="pt-4 border-t border-base-content/5">
                                <button
                                    className="w-full py-2 bg-primary text-white rounded font-bold hover:brightness-110"
                                    onClick={createNewLesson}
                                >
                                    <Plus size={16} className="inline mr-1" /> Create New Lesson
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function Save({ size, className }) {
    return <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>;
}
