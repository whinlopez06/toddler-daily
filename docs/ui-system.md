# Toddler Daily interface system

All modules use the Organic direction established by login. Source of truth: `frontend/src/styles.scss`; account-entry layout overrides live in `features/auth/auth-page.scss` and inherit global colors.

- Sand #E8DCC7 canvas, sage #8B9D83 page headers, moss #606C38 accents, darker #485329 primary controls, #283422 text, #536047 secondary text. Tonal sand #E0D2B9 panels separate content without shadows. Grain is decorative and cannot intercept input.
- Palatino/Georgia headings; Trebuchet/Arial body. No downloaded fonts. Main headings 34px desktop / 30px phone; section headings 22px. Existing account-entry headings keep their larger scale.
- Page header: title and scope/help on left; primary action on right, full width on small phones. Keep standard action wording. Back links appear within the heading area for child records.
- Panels use 24px radii and 20–24px padding. Controls use 16px radii, minimum 44px targets, visible focus, and matching hover/disabled states. Danger actions retain explicit wording and red-brown treatment.
- Main navigation has labeled SVG icons and aria-current. Phone navigation is an inline disclosure, with aria-controls/expanded and Escape dismissal; its DOM order matches its visual order. Child record tabs share the selected-state treatment.
- Use panel, section-heading, empty-state, record-row, section-tabs, button-row, field-help, field-error and notice classes rather than introducing per-module colors. Form fields have visible labels. Existing validation and server behavior are retained.
- The family dashboard shows first-child guidance only when there are no children; never fill real pages with sample records or invented totals.
- Responsive layouts stack forms and records at narrow widths. No layout animation; control transitions use 300ms easing and respect reduced motion.

This is a presentation standard, not a claim that every form workflow has completed end-to-end acceptance testing.
