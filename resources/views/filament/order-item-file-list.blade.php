<ul class="flex flex-col gap-2">
    @forelse ($files as $file)
        <li>
            <a
                href="{{ route($routeName, $file) }}"
                target="_blank"
                rel="noopener"
                class="flex items-center gap-2 text-sm font-medium text-primary-600 underline hover:text-primary-500"
            >
                <x-heroicon-o-arrow-down-tray class="h-4 w-4 shrink-0" />
                {{ $file->original_name }}
            </a>
        </li>
    @empty
        <li class="text-sm text-gray-500">Belum ada file.</li>
    @endforelse
</ul>
