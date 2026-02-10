import { useState, useEffect } from '@wordpress/element';

export default function StudentManager() {
    const [students, setStudents] = useState([]);

    useEffect(() => {
        fetch(`${wpApiSettings.root}smc/v1/instructor/students`, {
            headers: { 'X-WP-Nonce': wpApiSettings.nonce }
        })
            .then(res => res.json())
            .then(setStudents);
    }, []);

    return (
        <div className="smc-student-manager">
            <div className="smc-view-intro mb-8">
                <span className="smc-premium-badge">DIRECTORY</span>
                <h2 className="smc-premium-heading text-3xl mt-2">Student Management</h2>
            </div>
            <table className="smc-admin-table transition-all duration-500">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Progress</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {students.map(student => (
                        <tr key={student.id}>
                            <td>{student.name}</td>
                            <td>{student.email}</td>
                            <td>
                                <div className="smc-progress-inline">
                                    <div className="smc-progress-fill" style={{ width: `${student.progress}%` }}></div>
                                    <span>{student.progress}%</span>
                                </div>
                            </td>
                            <td>
                                <button className="smc-btn-secondary">View Details</button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
