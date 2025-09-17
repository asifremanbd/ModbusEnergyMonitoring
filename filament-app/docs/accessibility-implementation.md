# Accessibility Implementation

This document outlines the accessibility features implemented in the Teltonika Gateway Monitor application to ensure WCAG AA compliance and excellent user experience for all users.

## Overview

The application implements comprehensive accessibility features including:
- WCAG AA compliant color schemes and contrast ratios
- Full keyboard navigation support
- Screen reader compatibility with proper ARIA labels
- Responsive design for mobile and tablet devices
- High contrast and reduced motion support

## WCAG AA Compliance Features

### Color and Contrast

#### Color Scheme
- **Primary Colors**: Deep navy (#1e293b) for navigation, Industrial blue (#3b82f6) for accents
- **Status Colors**: Success green (#059669), Warning orange (#d97706), Danger red (#dc2626)
- **All color combinations meet WCAG AA contrast ratio requirements (4.5:1 minimum)**

#### High Contrast Mode Support
```css
@media (prefers-contrast: high) {
    /* Enhanced contrast for users who need it */
    .fi-btn:focus { outline: 4px solid #000000; }
    .status-online .status-dot { 
        background-color: #000000;
        border-color: #ffffff;
    }
}
```

### Keyboard Navigation

#### Focus Indicators
- **Enhanced focus rings**: 3px solid outline with 2px offset
- **High contrast focus**: 4px solid black outline in high contrast mode
- **Keyboard-only focus**: Visible focus indicators for all interactive elements

#### Tab Order
- **Logical tab sequence**: Skip links → Main navigation → Content → Footer
- **Skip links**: "Skip to main content" and "Skip to live data content"
- **Trapped focus**: Modal dialogs and dropdowns maintain focus within

#### Interactive Elements
```php
// All interactive elements include proper keyboard attributes
AccessibilityService::getKeyboardNavAttributes(0, 'button', 'Clear all filters')
// Returns: ['tabindex' => 0, 'role' => 'button', 'aria-label' => 'Clear all filters']
```

### Screen Reader Support

#### ARIA Labels and Landmarks
- **Page landmarks**: `role="main"`, `role="navigation"`, `role="search"`
- **Live regions**: `aria-live="polite"` for dynamic content updates
- **Status indicators**: Descriptive ARIA labels for all status elements

#### Table Accessibility
```php
// Proper table structure with accessibility attributes
AccessibilityService::getTableAttributes('Live data readings', $rowCount, $columnCount)
// Returns: ['role' => 'table', 'aria-label' => 'Live data readings', ...]
```

#### Form Controls
```php
// Form inputs with proper labeling and error handling
AccessibilityService::getFormControlAttributes('Gateway Name', true, 'help-text', 'error-id')
// Returns proper aria-label, aria-required, aria-describedby attributes
```

### Content Structure

#### Heading Hierarchy
- **H1**: Page titles (Dashboard, Live Data)
- **H2**: Major sections (KPIs, Fleet Status, Recent Events)
- **H3**: Subsections and card titles
- **Screen reader headings**: `class="sr-only"` for structure without visual hierarchy

#### Lists and Navigation
- **Semantic lists**: `role="list"` and `role="listitem"` for card grids
- **Navigation menus**: Proper ARIA attributes for menu items
- **Breadcrumbs**: Clear navigation path indication

## Responsive Design Implementation

### Breakpoint Strategy

#### Mobile-First Approach
```css
/* Extra small screens (< 475px) */
@media (max-width: 475px) {
    .fi-main { padding: 0.75rem; }
    .fi-dashboard-tiles { grid-template-columns: 1fr; }
}

/* Small screens (476px - 640px) */
@media (min-width: 476px) and (max-width: 640px) {
    .fi-dashboard-tiles { grid-template-columns: 1fr; }
}

/* Medium screens (641px - 768px) */
@media (min-width: 641px) and (max-width: 768px) {
    .fi-dashboard-tiles { grid-template-columns: repeat(2, 1fr); }
}
```

#### Touch Target Optimization
```css
/* Ensure minimum 44px touch targets on mobile */
@media (max-width: 768px) {
    button, .fi-btn, a, input[type="checkbox"] {
        min-height: 44px;
        min-width: 44px;
    }
}
```

### Layout Adaptations

#### Dashboard Layout
- **Mobile**: Single column KPI tiles, stacked gateway cards
- **Tablet**: Two-column KPI layout, responsive gateway grid
- **Desktop**: Three-column KPI layout, four-column gateway grid

#### Data Table Responsiveness
- **Horizontal scrolling**: Tables scroll horizontally on small screens
- **Sticky headers**: Column headers remain visible during scroll
- **Compact mode**: Reduced padding and font size for mobile viewing

#### Filter Controls
- **Mobile**: Vertically stacked filter controls
- **Tablet**: Horizontally arranged with wrapping
- **Desktop**: Full horizontal layout with adequate spacing

### Typography and Spacing

#### Responsive Typography
```css
/* Base font sizes adapt to screen size */
.fi-ta-table { font-size: 0.875rem; } /* Tablet */
@media (max-width: 475px) {
    .fi-ta-table { font-size: 0.75rem; } /* Mobile */
}
```

#### Adaptive Spacing
- **Mobile**: Reduced padding and margins (0.75rem base)
- **Tablet**: Standard spacing (1rem base)
- **Desktop**: Generous spacing (1.5rem+ base)

## Accessibility Service Features

### Status Indicators
```php
AccessibilityService::getStatusAriaLabel('online')
// Returns: "Gateway is online and responding"

AccessibilityService::getStatusAriaLabel('up')
// Returns: "Data point is receiving updates"
```

### Progress Indicators
```php
AccessibilityService::getProgressAttributes(75, 0, 100, 'Loading progress')
// Returns proper progressbar role and value attributes
```

### Expandable Content
```php
AccessibilityService::getExpandableAttributes(true, 'content-panel')
// Returns: ['aria-expanded' => 'true', 'aria-controls' => 'content-panel']
```

### Live Regions
```php
AccessibilityService::getLiveRegionAttributes('polite', false)
// Returns: ['aria-live' => 'polite', 'aria-atomic' => 'false']
```

## User Preference Support

### Reduced Motion
```css
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
```

### Dark Mode
```css
@media (prefers-color-scheme: dark) {
    :root {
        --sidebar-bg: #0f172a;
        --content-bg: #1e293b;
        --content-text: #f8fafc;
    }
}
```

### Print Styles
```css
@media print {
    .fi-sidebar, .fi-topbar, button { display: none !important; }
    .fi-ta-table { border-collapse: collapse; }
    .fi-ta-cell { border: 1px solid #000000; }
}
```

## Testing and Validation

### Automated Testing
- **Accessibility tests**: Comprehensive test suite covering ARIA attributes, keyboard navigation, and color contrast
- **Responsive tests**: Validation of responsive breakpoints and mobile adaptations
- **Screen reader tests**: Verification of proper semantic structure and labels

### Manual Testing Checklist
- [ ] Keyboard-only navigation through all interfaces
- [ ] Screen reader compatibility (NVDA, JAWS, VoiceOver)
- [ ] High contrast mode functionality
- [ ] Mobile device testing across different screen sizes
- [ ] Print functionality and layout

### Browser Support
- **Modern browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Mobile browsers**: iOS Safari 14+, Chrome Mobile 90+
- **Assistive technology**: Compatible with major screen readers

## Implementation Guidelines

### Adding New Components
1. **Include proper ARIA attributes** using AccessibilityService methods
2. **Ensure keyboard navigation** with proper tab order and focus management
3. **Test color contrast** using AccessibilityService::meetsContrastRequirement()
4. **Add responsive classes** following the established breakpoint strategy
5. **Include proper semantic HTML** with headings, lists, and landmarks

### Color Usage
- Always use the predefined color variables from the CSS theme
- Test new color combinations with the contrast ratio calculator
- Provide alternative indicators beyond color (icons, text, patterns)

### Interactive Elements
- Minimum 44px touch targets on mobile devices
- Clear focus indicators for keyboard users
- Descriptive labels and help text for form controls
- Proper error messaging with ARIA attributes

## Maintenance and Updates

### Regular Audits
- **Monthly accessibility audits** using automated tools (axe-core, Lighthouse)
- **Quarterly manual testing** with real assistive technology users
- **Annual WCAG compliance review** with external accessibility consultants

### Documentation Updates
- Keep this document updated with new accessibility features
- Document any accessibility-related bug fixes or improvements
- Maintain examples and code snippets for common patterns

### Training and Awareness
- Regular team training on accessibility best practices
- Code review checklist including accessibility considerations
- User testing sessions with diverse ability users

## Resources and References

### WCAG Guidelines
- [WCAG 2.1 AA Guidelines](https://www.w3.org/WAI/WCAG21/quickref/?versions=2.1&levels=aa)
- [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)
- [ARIA Authoring Practices Guide](https://www.w3.org/WAI/ARIA/apg/)

### Testing Tools
- [axe-core](https://github.com/dequelabs/axe-core) - Automated accessibility testing
- [WAVE](https://wave.webaim.org/) - Web accessibility evaluation tool
- [Lighthouse](https://developers.google.com/web/tools/lighthouse) - Performance and accessibility auditing

### Screen Readers
- [NVDA](https://www.nvaccess.org/) - Free Windows screen reader
- [JAWS](https://www.freedomscientific.com/products/software/jaws/) - Popular Windows screen reader
- [VoiceOver](https://www.apple.com/accessibility/mac/vision/) - Built-in macOS/iOS screen reader