import { useState, useEffect } from '@wordpress/element';
import { Mail, UserPlus, Pencil, X, BookOpen, Award, BarChart2, Calendar, CheckCircle, AlertCircle } from 'lucide-react';

export default function StudentManager() {
    const [students, setStudents] = useState([]);
    const [courses, setCourses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [isInviting, setIsInviting] = useState(false);
    const [isAdding, setIsAdding] = useState(false);
    const [editingStudent, setEditingStudent] = useState(null);
    const [savingStudent, setSavingStudent] = useState(false);
    const [statusSavingId, setStatusSavingId] = useState(null);

    // Detail View State
    const [selectedStudent, setSelectedStudent] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailData, setDetailData] = useState(null);

    // Invite Form State
    const [inviteEmail, setInviteEmail] = useState('');
    const [selectedCourseId, setSelectedCourseId] = useState('');
    const [inviteMessage, setInviteMessage] = useState('');
    const [sending, setSending] = useState(false);
    const [notice, setNotice] = useState(null);
    const [studentForm, setStudentForm] = useState({
        name: '',
        email: '',
        itemId: ''
    });

    const fetchData = async () => {
        try {
            const [studentRes, courseRes] = await Promise.all([
                fetch(`${wpApiSettings.root}smc/v1/instructor/students`, { headers: { 'X-WP-Nonce': wpApiSettings.nonce } }),
                fetch(`${wpApiSettings.root}smc/v1/instructor/courses-list`, { headers: { 'X-WP-Nonce': wpApiSettings.nonce } })
            ]);

            const studentData = await studentRes.json();
            const courseData = await courseRes.json();

            setStudents(studentData);
            setCourses(courseData);
            if (courseData.length > 0) {
                setSelectedCourseId((prev) => {
                    const hasCurrent = courseData.some((course) => String(course.id) === String(prev));
                    return hasCurrent ? prev : String(courseData[0].id);
                });
            } else {
                setSelectedCourseId('');
            }
        } catch (err) {
            console.error("Failed to load data", err);
        } finally {
            setLoading(false);
        }
    };

    const fetchStudentDetails = async (studentId) => {
        setDetailLoading(true);
        setSelectedStudent(studentId);
        try {
            const res = await fetch(`${wpApiSettings.root}smc/v1/instructor/students/${studentId}`, {
                headers: { 'X-WP-Nonce': wpApiSettings.nonce }
            });
            const data = await res.json();
            setDetailData(data);
        } catch (err) {
            console.error("Failed to load student details", err);
        } finally {
            setDetailLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, []);

    useEffect(() => {
        if (!notice) return undefined;
        const timeout = setTimeout(() => setNotice(null), notice.duration || 3200);
        return () => clearTimeout(timeout);
    }, [notice]);

    const normalizeErrorMessage = (input) => {
        if (!input) return 'Something went wrong while processing your request.';
        const text = String(input).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        if (!text) return 'Something went wrong while processing your request.';
        if (/critical error on this website/i.test(text)) {
            return 'Server error while processing your request. Please try again or contact support.';
        }
        return text;
    };

    const resetStudentForm = () => {
        setStudentForm({
            name: '',
            email: '',
            itemId: ''
        });
    };

    const openAddModal = () => {
        resetStudentForm();
        setEditingStudent(null);
        setIsAdding(true);
    };

    const openEditModal = (student) => {
        setStudentForm({
            name: student?.name || '',
            email: student?.email || '',
            itemId: ''
        });
        setEditingStudent(student);
        setIsAdding(false);
    };

    const closeStudentModal = () => {
        setIsAdding(false);
        setEditingStudent(null);
        resetStudentForm();
    };

    const handleStudentSave = async (e) => {
        e.preventDefault();
        if (!studentForm.email) return;

        const isEdit = Boolean(editingStudent?.id);
        const endpoint = isEdit
            ? `${wpApiSettings.root}smc/v1/instructor/students/${editingStudent.id}`
            : `${wpApiSettings.root}smc/v1/instructor/students`;

        setSavingStudent(true);
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    name: studentForm.name,
                    email: studentForm.email,
                    item_id: studentForm.itemId || null
                })
            });

            const result = await response.json();
            if (!response.ok) {
                throw new Error(normalizeErrorMessage(result?.message || result?.error || 'Failed to save student.'));
            }

            setNotice({
                status: 'success',
                title: isEdit ? 'Student Updated' : 'Student Added',
                text: isEdit ? 'Student details were updated successfully.' : 'Student has been added successfully.',
                duration: 3200
            });
            closeStudentModal();
            fetchData();
        } catch (err) {
            console.error("Student save failed", err);
            setNotice({
                status: 'error',
                title: isEdit ? 'Update Failed' : 'Add Failed',
                text: normalizeErrorMessage(err?.message || 'Failed to save student.'),
                duration: 3800
            });
        } finally {
            setSavingStudent(false);
        }
    };

    const handleToggleDisabled = async (student) => {
        if (!student?.id) return;

        const targetDisabled = !Boolean(student.disabled);
        setStatusSavingId(student.id);

        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/students/${student.id}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    disabled: targetDisabled
                })
            });

            const result = await response.json();
            if (!response.ok) {
                throw new Error(normalizeErrorMessage(result?.message || result?.error || 'Failed to update student status.'));
            }

            setNotice({
                status: 'success',
                title: targetDisabled ? 'Student Disabled' : 'Student Enabled',
                text: targetDisabled
                    ? `${student.email} can no longer sign in.`
                    : `${student.email} can sign in again.`,
                duration: 3200
            });
            fetchData();
        } catch (err) {
            console.error("Status update failed", err);
            setNotice({
                status: 'error',
                title: 'Status Update Failed',
                text: normalizeErrorMessage(err?.message || 'Failed to update student status.'),
                duration: 3800
            });
        } finally {
            setStatusSavingId(null);
        }
    };

    const handleInvite = async (e) => {
        e.preventDefault();
        if (!inviteEmail || !selectedCourseId) return;

        setSending(true);
        try {
            const response = await fetch(`${wpApiSettings.root}smc/v1/instructor/courses/${selectedCourseId}/invite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    email: inviteEmail,
                    message: inviteMessage
                })
            });

            const result = await response.json();
            if (!response.ok) {
                throw new Error(normalizeErrorMessage(result?.message || result?.error || 'Failed to send invitation.'));
            }

            const inviteResults = result?.results || {};
            if (inviteResults.error) {
                throw new Error(normalizeErrorMessage(inviteResults.error));
            }

            const inviteStatus = inviteResults[inviteEmail];
            if (typeof inviteStatus === 'string' && /(failed|error|invalid)/i.test(inviteStatus)) {
                throw new Error(normalizeErrorMessage(inviteStatus));
            }

            setNotice({
                status: 'success',
                title: 'Invitation Sent',
                text: `${inviteEmail} has been invited successfully.`,
                duration: 3200
            });
            setInviteEmail('');
            setInviteMessage('');
            setIsInviting(false);
            fetchData();
        } catch (err) {
            console.error("Invite failed", err);
            setNotice({
                status: 'error',
                title: 'Invite Failed',
                text: normalizeErrorMessage(err?.message || "Failed to send invitation."),
                duration: 3800
            });
        } finally {
            setSending(false);
        }
    };

    if (loading) return <div>Loading...</div>;

    return (
        <div className="smc-student-manager relative">
            <header className="smc-builder-header mb-10 flex justify-between items-end">
                <div className="smc-view-title text-left">
                    <span className="smc-premium-badge">DIRECTORY</span>
                    <h2 className="smc-premium-heading text-3xl mt-2">Student Management</h2>
                </div>
                <div className="flex flex-wrap items-center gap-3 justify-end">
                    <button
                        className="smc-btn-secondary"
                        onClick={openAddModal}
                    >
                        <UserPlus size={17} className="mr-2" /> Add Student
                    </button>
                    <button
                        className="smc-btn-primary"
                        onClick={() => setIsInviting(true)}
                    >
                        <Mail size={18} className="mr-2" /> Invite Student
                    </button>
                </div>
            </header>

            {(isAdding || editingStudent) && (
                <div className="smc-modal-overlay">
                    <div className="smc-modal smc-invite-modal" style={{ maxWidth: '620px' }}>
                        <div className="flex justify-between items-center mb-2">
                            <h3>{editingStudent ? 'Edit Student' : 'Add Student'}</h3>
                            <button onClick={closeStudentModal} className="smc-invite-close-btn" aria-label="Close student form">
                                <X size={18} />
                            </button>
                        </div>
                        <p className="smc-student-modal-subtitle">
                            {editingStudent
                                ? 'Update student details and optionally assign a new course or plan.'
                                : 'Create a student manually and assign a course or plan immediately.'}
                        </p>
                        <form onSubmit={handleStudentSave}>
                            <div className="smc-form-group">
                                <label>Full Name</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="Student Name"
                                    className="smc-invite-input"
                                    value={studentForm.name}
                                    onChange={(e) => setStudentForm((prev) => ({ ...prev, name: e.target.value }))}
                                    autoFocus
                                />
                            </div>

                            <div className="smc-form-group">
                                <label>Email Address</label>
                                <input
                                    type="email"
                                    required
                                    placeholder="student@example.com"
                                    className="smc-invite-input"
                                    value={studentForm.email}
                                    onChange={(e) => setStudentForm((prev) => ({ ...prev, email: e.target.value }))}
                                />
                            </div>

                            <div className="smc-form-group">
                                <label>Assign Course or Plan (Optional)</label>
                                <select
                                    className="smc-invite-input"
                                    value={studentForm.itemId}
                                    onChange={(e) => setStudentForm((prev) => ({ ...prev, itemId: e.target.value }))}
                                >
                                    <option value="">No assignment</option>
                                    {courses.map((c) => (
                                        <option key={c.id} value={c.id}>{c.title}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="smc-modal-actions">
                                <button type="button" onClick={closeStudentModal} className="smc-invite-btn smc-invite-btn-secondary">Cancel</button>
                                <button type="submit" className="smc-invite-btn smc-invite-btn-primary" disabled={savingStudent}>
                                    {savingStudent ? (editingStudent ? 'Saving...' : 'Adding...') : (editingStudent ? 'Save Changes' : 'Add Student')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Invite Modal */}
            {isInviting && (
                <div className="smc-modal-overlay">
                    <div className="smc-modal smc-invite-modal" style={{ maxWidth: '560px' }}>
                        <div className="flex justify-between items-center mb-6">
                            <h3>Invite Student</h3>
                            <button onClick={() => setIsInviting(false)} className="smc-invite-close-btn" aria-label="Close invite form">
                                <X size={18} />
                            </button>
                        </div>
                        <form onSubmit={handleInvite}>
                            <div className="smc-form-group">
                                <label>Email Address</label>
                                <input
                                    type="email"
                                    required
                                    placeholder="student@example.com"
                                    className="smc-invite-input"
                                    value={inviteEmail}
                                    onChange={(e) => setInviteEmail(e.target.value)}
                                    autoFocus
                                />
                            </div>

                            <div className="smc-form-group">
                                <label>Enroll in Course or Plan</label>
                                <select
                                    className="smc-invite-input"
                                    value={selectedCourseId}
                                    onChange={(e) => setSelectedCourseId(e.target.value)}
                                >
                                    <option value="" disabled>Select an option...</option>
                                    {courses.map(c => (
                                        <option key={c.id} value={c.id}>{c.title}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="smc-form-group">
                                <label>Personal Message (Optional)</label>
                                <textarea
                                    className="smc-invite-input smc-invite-textarea"
                                    rows="3"
                                    value={inviteMessage}
                                    onChange={(e) => setInviteMessage(e.target.value)}
                                    placeholder="Welcome to the course..."
                                />
                            </div>

                            <div className="smc-modal-actions">
                                <button type="button" onClick={() => setIsInviting(false)} className="smc-invite-btn smc-invite-btn-secondary">Cancel</button>
                                <button type="submit" className="smc-invite-btn smc-invite-btn-primary" disabled={sending || courses.length === 0}>
                                    {sending ? 'Sending...' : 'Send Invitation'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {notice && (
                <div className="smc-toaster smc-instructor-toaster">
                    <div className={`smc-toast ${notice.status}`}>
                        <div className="toast-icon">
                            {notice.status === 'success' ? <CheckCircle size={20} /> : <AlertCircle size={20} />}
                        </div>
                        <div className="toast-content">
                            <h4>{notice.title}</h4>
                            <p>{notice.text}</p>
                        </div>
                        <div className="toast-timer" style={{ animationDuration: `${notice.duration || 3200}ms` }}></div>
                    </div>
                </div>
            )}

            {/* Student Detail Slide-over */}
            {selectedStudent && (
                <div className="smc-slide-over-backdrop" onClick={() => setSelectedStudent(null)}>
                    <div className="smc-slide-over-panel" onClick={e => e.stopPropagation()}>
                        {detailLoading || !detailData ? (
                            <div className="smc-loading">Loading Profile...</div>
                        ) : (
                            <>
                                <div className="smc-detail-header">
                                    <div className="flex items-center gap-6">
                                        <div className="relative">
                                            <img
                                                src={detailData.avatar}
                                                alt={detailData.name}
                                                className="w-20 h-20 rounded-2xl border-2 border-white/10 shadow-lg object-cover"
                                            />
                                            <div className="absolute -bottom-2 -right-2 bg-green-500 w-5 h-5 rounded-full border-4 border-gray-900"></div>
                                        </div>

                                        <div>
                                            <h3 className="text-3xl font-bold text-[#F1F5F9]">
                                                {detailData.name}
                                            </h3>
                                            <p className="text-smc-text-muted font-medium mt-1">{detailData.email}</p>
                                            <p className="text-xs text-gray-400 mt-3 flex items-center gap-2 font-bold uppercase tracking-wider">
                                                <Calendar size={12} className="text-teal-500" />
                                                Joined {detailData.registered}
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => setSelectedStudent(null)}
                                        className="w-10 h-10 rounded-full flex items-center justify-center bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white transition-all"
                                    >
                                        <X size={20} />
                                    </button>
                                </div>

                                <div className="smc-detail-content space-y-8">
                                    {detailData.business_identity && (
                                        <div className="smc-detail-section">
                                            <div className="flex items-center gap-2 mb-4 text-[10px] font-black uppercase tracking-[0.2em] text-teal-500/60">
                                                <BarChart2 size={12} />
                                                <span>Business Identity</span>
                                            </div>
                                            <div className="smc-glass-card p-6 border border-white/5 bg-gradient-to-br from-white/5 to-transparent rounded-2xl">
                                                <div className="flex justify-between items-center">
                                                    <div>
                                                        <p className="text-xl font-black text-white">{detailData.business_identity.stage || 'Unknown Stage'}</p>
                                                        <p className="text-sm text-smc-text-muted mt-1">{detailData.business_identity.industry || 'Unknown Industry'}</p>
                                                    </div>
                                                    <div className="text-right">
                                                        <div className="text-4xl font-black text-amber-500 tracking-tighter">
                                                            {detailData.business_identity.score || 0}
                                                        </div>
                                                        <span className="text-[10px] text-smc-text-muted font-black tracking-[0.2em] uppercase">Viability Score</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    <div className="smc-detail-section">
                                        <div className="flex items-center gap-2 mb-4 text-[10px] font-black uppercase tracking-[0.2em] text-teal-500/60">
                                            <BookOpen size={12} />
                                            <span>Enrolled Courses</span>
                                        </div>
                                        {detailData.enrollments.length === 0 ? (
                                            <div className="p-6 rounded-2xl bg-white/5 border border-white/5 text-gray-500 italic text-sm text-center">
                                                No active enrollments found for this student.
                                            </div>
                                        ) : (
                                            <div className="space-y-4">
                                                {detailData.enrollments.map(enr => (
                                                    <div key={enr.course_id} className="smc-detail-card group bg-white/5 p-5 rounded-2xl border border-white/5 hover:border-teal-500/30 transition-all">
                                                        <div className="flex justify-between items-start mb-3">
                                                            <h5 className="font-bold text-gray-200 group-hover:text-teal-400 transition-colors">{enr.title}</h5>
                                                            <span className={`text-[10px] font-black uppercase tracking-wider px-2 py-1 rounded-md ${enr.status === 'completed' ? 'bg-green-500/10 text-green-400' : 'bg-blue-500/10 text-blue-400'}`}>
                                                                {enr.status}
                                                            </span>
                                                        </div>
                                                        <div className="w-full bg-gray-700/30 h-1.5 rounded-full overflow-hidden mb-3">
                                                            <div className="bg-gradient-to-r from-teal-600 to-teal-400 h-full transition-all duration-1000 ease-out" style={{ width: `${enr.progress}%` }}></div>
                                                        </div>
                                                        <div className="flex justify-between text-[10px] text-gray-500 font-black uppercase tracking-widest">
                                                            <span>{enr.progress}% Complete</span>
                                                            <span>Last: {enr.last_accessed}</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    <div className="smc-detail-section">
                                        <div className="flex items-center gap-2 mb-4 text-[10px] font-black uppercase tracking-[0.2em] text-teal-500/60">
                                            <Award size={12} />
                                            <span>Quiz History</span>
                                        </div>
                                        {detailData.quizzes.length === 0 ? (
                                            <div className="p-6 rounded-2xl bg-white/5 border border-white/5 text-gray-500 italic text-sm text-center">
                                                No quiz submissions recorded.
                                            </div>
                                        ) : (
                                            <div className="space-y-3">
                                                {detailData.quizzes.map(q => (
                                                    <div key={q.id} className="flex justify-between items-center p-4 bg-white/5 rounded-2xl border border-white/5 hover:border-teal-500/30 transition-all">
                                                        <div>
                                                            <p className="font-bold text-gray-200">{q.title}</p>
                                                            <p className="text-[10px] text-gray-500 mt-1 font-black uppercase tracking-widest">{q.date}</p>
                                                        </div>
                                                        <div className="text-right">
                                                            <span className={`text-xl font-black ${q.score >= 80 ? 'text-green-400' : q.score >= 50 ? 'text-amber-400' : 'text-red-400'}`}>
                                                                {q.score}%
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}

            <div className="smc-glass-card rounded-3xl overflow-hidden border border-white/5">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-white/5 text-teal-500/60 text-[10px] font-black uppercase tracking-[0.2em]">
                        <tr>
                            <th className="p-6">Student</th>
                            <th className="p-6">Status</th>
                            <th className="p-6">Activity</th>
                            <th className="p-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-white/5">
                        {students.length === 0 ? (
                            <tr><td colSpan="4" className="p-8 text-center text-gray-500">No students found.</td></tr>
                        ) : (
                            students.map(student => (
                                <tr key={student.id} className="hover:bg-white/5 transition-colors group">
                                    <td className="p-6">
                                        <div className="flex items-center gap-4">
                                            <img src={student.avatar} alt="" className="w-10 h-10 rounded-xl bg-white/10 object-cover" />
                                            <div>
                                                <div className="font-bold text-white text-base leading-none">{student.name}</div>
                                                <div className="text-[10px] text-gray-500 mt-1 uppercase tracking-widest font-black">{student.email}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="p-6">
                                        <div className="flex flex-col gap-1.5">
                                            {student.completed_count > 0 && (
                                                <span className="inline-flex w-fit items-center px-2 py-1 rounded text-[10px] font-black uppercase tracking-widest bg-green-500/10 text-green-400 border border-green-500/20">
                                                    {student.completed_count} Completed
                                                </span>
                                            )}
                                            {student.disabled && (
                                                <span className="inline-flex w-fit items-center px-2 py-1 rounded text-[10px] font-black uppercase tracking-widest bg-red-500/10 text-red-500 border border-red-500/20">
                                                    Disabled
                                                </span>
                                            )}
                                            <span className="inline-flex w-fit items-center px-2 py-1 rounded text-[10px] font-black uppercase tracking-widest bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                                Plan: {student.plan || 'free'}
                                            </span>
                                            {student.enrollment_count > 0 && (
                                                <span className="inline-flex w-fit items-center px-2 py-1 rounded text-[10px] font-black uppercase tracking-widest bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                                    {student.enrollment_count} Active
                                                </span>
                                            )}
                                            {student.completed_count === 0 && student.enrollment_count === 0 && (
                                                <span className="text-gray-600 text-[10px] font-black uppercase tracking-widest">No active enrollments</span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="p-6 text-gray-400 text-[10px] font-black uppercase tracking-widest">
                                        Last active: {student.last_active ? new Date(student.last_active).toLocaleDateString() : 'Never'}
                                    </td>
                                    <td className="p-6 text-right">
                                        <div className="inline-flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button
                                                onClick={() => openEditModal(student)}
                                                className="smc-btn-secondary text-xs py-1 px-3"
                                            >
                                                <Pencil size={12} className="mr-1" /> Edit
                                            </button>
                                            <button
                                                onClick={() => fetchStudentDetails(student.id)}
                                                className="smc-btn-secondary text-xs py-1 px-3"
                                            >
                                                View Details
                                            </button>
                                            <button
                                                onClick={() => handleToggleDisabled(student)}
                                                className={`smc-btn-secondary text-xs py-1 px-3 ${student.disabled ? 'smc-status-enable' : 'smc-status-disable'}`}
                                                disabled={statusSavingId === student.id}
                                            >
                                                {statusSavingId === student.id
                                                    ? 'Saving...'
                                                    : (student.disabled ? 'Enable' : 'Disable')}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
