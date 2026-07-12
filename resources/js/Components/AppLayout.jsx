import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Sidebar from './Sidebar';
import Header from './Header';

export default function AppLayout({ children, title }) {
    const { flash } = usePage().props;
    const [flashMessage, setFlashMessage] = useState(null);

    useEffect(() => {
        if (flash?.success) {
            setFlashMessage({ type: 'success', text: flash.success });
        } else if (flash?.error) {
            setFlashMessage({ type: 'error', text: flash.error });
        }
    }, [flash]);

    // Auto-dismiss after 4 seconds
    useEffect(() => {
        if (flashMessage) {
            const timer = setTimeout(() => setFlashMessage(null), 4000);
            return () => clearTimeout(timer);
        }
    }, [flashMessage]);

    return (
        <div className="flex min-h-screen bg-gray-100">
            <Sidebar />

            <div className="ml-[250px] flex flex-1 flex-col">
                <Header title={title} />

                <main className="flex-1 p-8">
                    {/* Flash notifications */}
                    {flashMessage && (
                        <div
                            className={`mb-6 flex items-center justify-between rounded-lg px-4 py-3 text-sm font-medium shadow-sm ${
                                flashMessage.type === 'success'
                                    ? 'bg-green-50 text-green-800 border border-green-200'
                                    : 'bg-red-50 text-red-800 border border-red-200'
                            }`}
                        >
                            <span>{flashMessage.text}</span>
                            <button
                                onClick={() => setFlashMessage(null)}
                                className="ml-4 text-lg leading-none opacity-50 hover:opacity-100"
                            >
                                ×
                            </button>
                        </div>
                    )}

                    {children}
                </main>
            </div>
        </div>
    );
}
