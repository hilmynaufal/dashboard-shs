@props(['name'])
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('home')<path d="m3 10 9-7 9 7v10H3zM9 20v-7h6v7"/>@break
@case('users')<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v2"/>@break
@case('user')<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>@break
@case('user-check')<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20v-1.5a6.5 6.5 0 0 1 11-4.7M15 17l2.5 2.5L22 15"/>@break
@case('logout')<path d="M9 4H4v16h5m5-12 4 4-4 4m-5-4h12"/>@break
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('arrow')<path d="m9 5 7 7-7 7"/>@break
@case('arrow-left')<path d="M19 12H5m6-6-6 6 6 6"/>@break
@case('chevron-down')<path d="m6 9 6 6 6-6"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('search')<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>@break
@case('building')<path d="M5 21V5l14-2v18M2 21h20M9 8h2m4-1h1M9 12h2m4 0h1M9 16h2m4 0h1M10 21v-2h4v2"/>@break
@case('shield')<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6zM8 12l3 3 5-6"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18"/>@break
@case('catalog')<path d="M4 4h7v7H4zm9 0h7v7h-7zM4 13h7v7H4zm9 0h7v7h-7z"/>@break
@case('chart')<path d="M4 20V4m0 16h16M8 16v-4m4 4V8m4 8v-6"/>@break
@case('table')<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18M9 10v10"/>@break
@case('folder')<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>@break
@case('clipboard')<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/>@break
@case('message')<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12.5h5"/>@break
@case('file')<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>@break
@case('file-check')<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 14l2 2 4-4.5"/>@break
@case('archive')<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4"/>@break
@case('history')<path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1L3.5 8.5M3.5 4v4.5H8M12 7.5V12l3 2"/>@break
@case('database')<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>@break
@case('edit')<path d="M4 20h4L19 9l-4-4L4 16zM13 7l4 4"/>@break
@case('download')<path d="M12 4v11m-5-5 5 5 5-5M4 20h16"/>@break
@case('upload')<path d="M12 16V5m-5 5 5-5 5 5M4 20h16"/>@break
@case('paperclip')<path d="m20 11-8.5 8.5a5 5 0 0 1-7-7L13 4a3.3 3.3 0 0 1 4.700 4.700L9.500 17a1.700 1.700 0 0 1-2.400-2.400L15 6.700"/>@break
@case('alert')<path d="M12 4 2.500 20h19zM12 10v4.500M12 17.200v.300"/>@break
@case('info')<circle cx="12" cy="12" r="9"/><path d="M12 11v5.500M12 7.500v.300"/>@break
@case('check')<path d="m5 12.500 4.500 4.500L19 7"/>@break
@case('check-circle')<circle cx="12" cy="12" r="9"/><path d="m8 12.300 2.800 2.800L16 9.500"/>@break
@case('x')<path d="M6 6l12 12M18 6 6 18"/>@break
@case('more')<circle cx="5" cy="12" r="1.200"/><circle cx="12" cy="12" r="1.200"/><circle cx="19" cy="12" r="1.200"/>@break
@case('send')<path d="M21 3 10 14M21 3l-7 18-4-7-7-4z"/>@break
@case('eye')<path d="M2 12s3.600-7 10-7 10 7 10 7-3.600 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>@break
@case('eye-off')<path d="M3 3l18 18M10.600 5.200A9.600 9.600 0 0 1 12 5c6.400 0 10 7 10 7a17 17 0 0 1-3.300 4.200M6.300 6.400A17 17 0 0 0 2 12s3.600 7 10 7a9.500 9.500 0 0 0 4.200-1M9.900 9.900a3 3 0 0 0 4.200 4.200"/>@break
@case('trash')<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/>@break
@case('filter')<path d="M4 6h16M7 12h10M10 18h4"/>@break
@case('coins')<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v5c0 1.700 2.700 3 6 3M3 12v5c0 1.700 2.700 3 6 3"/><ellipse cx="15" cy="14" rx="6" ry="3"/><path d="M9 14v4c0 1.700 2.700 3 6 3s6-1.300 6-3v-4"/>@break
@case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.500 2"/>@break
@case('refresh')<path d="M20 11a8 8 0 0 0-14.500-4L3.500 9M3.500 4.500V9H8M4 13a8 8 0 0 0 14.500 4l2-2M20.500 19.500V15H16"/>@break
@case('lock')<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>@break
@case('layers')<path d="m12 3 9 5-9 5-9-5zM3 13l9 5 9-5M3 17.500l9 5 9-5"/>@break
@case('swap')<path d="M4 8h15m-4-4 4 4-4 4M20 16H5m4-4-4 4 4 4"/>@break
@case('external')<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>@break
@endswitch
</svg>
