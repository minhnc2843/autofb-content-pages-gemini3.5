import { Link, usePage } from '@inertiajs/react';

const navItems = [
    { href: '/', label: 'Dashboard', icon: '📊' },
    { href: '/topics', label: 'Topics', icon: '📝' },
    { href: '/pexels', label: 'Pexels Search', icon: '🔍' },
    { href: '/queue', label: 'Queue', icon: '📋' },
    { href: '/calendar', label: 'Calendar', icon: '🗓' },
    { href: '/strategy', label: 'Strategy Engine', icon: '🎯' },
    { href: '/settings', label: 'Settings', icon: '⚙️' },
];

export default function Sidebar() {
    const { url } = usePage();

    const isActive = (href) => {
        if (href === '/') return url === '/';
        return url.startsWith(href);
    };

    return (
        <aside className="fixed inset-y-0 left-0 z-30 flex w-[250px] flex-col bg-gray-900 text-white">
            {/* App branding */}
            <div className="flex h-16 items-center gap-3 border-b border-gray-700 px-6">
                <span className="text-2xl">📘</span>
                <span className="text-lg font-bold tracking-tight">FB Content Planner</span>
            </div>

            {/* Navigation */}
            <nav className="flex-1 space-y-1 px-3 py-4">
                {navItems.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                            isActive(item.href)
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                        }`}
                    >
                        <span className="text-lg">{item.icon}</span>
                        {item.label}
                    </Link>
                ))}
            </nav>

            {/* Footer */}
            <div className="border-t border-gray-700 px-6 py-4">
                <p className="text-xs text-gray-500">Phase 1 — Blueprint Mode</p>
            </div>
        </aside>
    );
}
