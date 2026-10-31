{{-- PART D — brand palette as CSS variables. Put on the <html> element's style
     attribute so it beats any stylesheet rule and works on pages that do not
     use the app layout (login, receipt). Values come from the settings table. --}}
--brand-primary: {{ $brandPrimary }};
--brand-primary-dark: color-mix(in srgb, {{ $brandPrimary }} 84%, #000);
--brand-accent: {{ $brandAccent }};
