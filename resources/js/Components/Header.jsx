export default function Header({ title }) {
    const today = new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    return (
        <header className="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-8">
            <h1 className="text-xl font-semibold text-gray-800">{title}</h1>
            <span className="text-sm text-gray-500">{today}</span>
        </header>
    );
}
