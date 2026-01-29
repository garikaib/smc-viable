import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

export default function LeadList() {
    const [leads, setLeads] = useState([]);
    const [loading, setLoading] = useState(true);
    const [deleting, setDeleting] = useState(null); // Track which lead is being deleted

    useEffect(() => {
        fetchLeads();
    }, []);

    const fetchLeads = () => {
        setLoading(true);
        apiFetch({ path: '/smc/v1/leads' })
            .then(data => {
                setLeads(data);
                setLoading(false);
            })
            .catch(err => {
                console.error(err);
                setLoading(false);
            });
    };

    const handleDelete = async (id, name) => {
        if (!window.confirm(`Are you sure you want to delete the lead "${name}"? This action cannot be undone.`)) {
            return;
        }

        setDeleting(id);
        try {
            await apiFetch({
                path: `/smc/v1/leads/${id}`,
                method: 'DELETE',
            });
            setLeads(leads.filter(l => l.id !== id));
        } catch (err) {
            console.error('Failed to delete lead:', err);
            alert('Failed to delete lead. Please try again.');
        } finally {
            setDeleting(null);
        }
    };

    const handleExportExcel = async () => {
        // Dynamically load SheetJS if not already loaded
        if (!window.XLSX) {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        const XLSX = window.XLSX;

        // Prepare data for Excel
        const excelData = leads.map(l => ({
            'Date': new Date(l.date).toLocaleString(),
            'Name': l.name || '',
            'Email': l.email,
            'Phone': l.phone,
            'Quiz ID': l.quiz_id
        }));

        // Create workbook and worksheet
        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Leads');

        // Auto-size columns
        const colWidths = [
            { wch: 20 }, // Date
            { wch: 25 }, // Name
            { wch: 30 }, // Email
            { wch: 15 }, // Phone
            { wch: 10 }, // Quiz ID
        ];
        ws['!cols'] = colWidths;

        // Download
        XLSX.writeFile(wb, `smc_leads_${new Date().toISOString().split('T')[0]}.xlsx`);
    };

    if (loading) return <Spinner />;

    return (
        <div className="smc-leads-dashboard p-4">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">{__('Leads Dashboard', 'smc-viable')}</h1>
                <div className="flex gap-2">
                    <Button isSecondary onClick={fetchLeads}>
                        {__('Refresh', 'smc-viable')}
                    </Button>
                    <Button isPrimary onClick={handleExportExcel} disabled={leads.length === 0}>
                        {__('Export Excel', 'smc-viable')}
                    </Button>
                </div>
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
                                <th className="p-4 font-semibold">{__('Actions', 'smc-viable')}</th>
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
                                    <td className="p-4">
                                        <Button
                                            isDestructive
                                            isSmall
                                            isBusy={deleting === lead.id}
                                            disabled={deleting !== null}
                                            onClick={() => handleDelete(lead.id, lead.name)}
                                        >
                                            {deleting === lead.id ? __('Deleting...', 'smc-viable') : __('Delete', 'smc-viable')}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <div className="mt-4 text-sm text-gray-500">
                {__('Total leads:', 'smc-viable')} {leads.length}
            </div>
        </div>
    );
}

