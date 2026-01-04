import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

export default function LeadList() {
    const [leads, setLeads] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        apiFetch({ path: '/smc/v1/leads' })
            .then(data => {
                setLeads(data);
                setLoading(false);
            })
            .catch(err => {
                console.error(err);
                setLoading(false);
            });
    }, []);

    const handleExport = () => {
        // Simple CSV Export
        const headers = ['Date', 'Name', 'Email', 'Phone', 'Quiz ID'];
        const rows = leads.map(l => [
            l.date,
            `"${l.name || ''}"`, // Quote to handle commas
            l.email,
            l.phone,
            l.quiz_id
        ]);

        const csvContent = "data:text/csv;charset=utf-8,"
            + headers.join(",") + "\n"
            + rows.map(e => e.join(",")).join("\n");

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "smc_leads_export.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    if (loading) return <Spinner />;

    return (
        <div className="smc-leads-dashboard p-4">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">{__('Leads Dashboard', 'smc-viable')}</h1>
                <Button isPrimary onClick={handleExport} disabled={leads.length === 0}>
                    {__('Export CSV', 'smc-viable')}
                </Button>
            </div>

            {leads.length === 0 ? (
                <div className="text-center text-gray-500 py-10 bg-white rounded-lg shadow">
                    {__('No leads found yet.', 'smc-viable')}
                </div>
            ) : (
                <div className="overflow-x-auto bg-white rounded-lg shadow">
                    <table className="table w-full">
                        <thead>
                            <tr className="bg-gray-50 text-left">
                                <th className="p-4 font-semibold">{__('Date', 'smc-viable')}</th>
                                <th className="p-4 font-semibold">{__('Name', 'smc-viable')}</th>
                                <th className="p-4 font-semibold">{__('Email', 'smc-viable')}</th>
                                <th className="p-4 font-semibold">{__('Phone', 'smc-viable')}</th>
                                <th className="p-4 font-semibold">{__('Quiz ID', 'smc-viable')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leads.map(lead => (
                                <tr key={lead.id} className="border-t hover:bg-gray-50">
                                    <td className="p-4 text-sm text-gray-600">{new Date(lead.date).toLocaleString()}</td>
                                    <td className="p-4 font-medium">{lead.name}</td>
                                    <td className="p-4 text-blue-600">{lead.email}</td>
                                    <td className="p-4 text-gray-600">{lead.phone}</td>
                                    <td className="p-4 text-gray-500 text-xs">{lead.quiz_id}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
