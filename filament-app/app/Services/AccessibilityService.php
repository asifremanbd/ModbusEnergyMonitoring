<?php

namespace App\Services;

class AccessibilityService
{
    /**
     * Generate ARIA label for status indicators
     */
    public static function getStatusAriaLabel(string $status): string
    {
        return match ($status) {
            'online' => 'Gateway is online and responding',
            'offline' => 'Gateway is offline or not responding',
            'unknown' => 'Gateway status is unknown',
            'up' => 'Data point is receiving updates',
            'down' => 'Data point is not receiving updates',
            'good' => 'Data quality is good',
            'bad' => 'Data quality is poor',
            'uncertain' => 'Data quality is uncertain',
            default => 'Status is ' . $status,
        };
    }

    /**
     * Get WCAG AA compliant color contrast ratios
     */
    public static function getContrastColors(): array
    {
        return [
            'success' => [
                'bg' => '#059669',
                'text' => '#ffffff',
                'contrast_ratio' => 4.5,
            ],
            'warning' => [
                'bg' => '#d97706',
                'text' => '#ffffff',
                'contrast_ratio' => 4.5,
            ],
            'danger' => [
                'bg' => '#dc2626',
                'text' => '#ffffff',
                'contrast_ratio' => 4.5,
            ],
            'info' => [
                'bg' => '#0284c7',
                'text' => '#ffffff',
                'contrast_ratio' => 4.5,
            ],
        ];
    }

    /**
     * Generate keyboard navigation attributes
     */
    public static function getKeyboardNavAttributes(int $tabIndex = 0, string $role = 'button', string $label = 'Interactive element'): array
    {
        return [
            'tabindex' => $tabIndex,
            'role' => $role,
            'aria-label' => $label,
        ];
    }

    /**
     * Generate screen reader friendly table headers
     */
    public static function getTableHeaderAttributes(string $columnName, bool $sortable = false): array
    {
        $attributes = [
            'scope' => 'col',
            'role' => 'columnheader',
        ];

        if ($sortable) {
            $attributes['aria-label'] = 'Sort by ' . $columnName;
            $attributes['tabindex'] = '0';
        } else {
            $attributes['aria-label'] = $columnName . ' column header';
        }

        return $attributes;
    }

    /**
     * Generate ARIA attributes for data tables
     */
    public static function getTableAttributes(string $caption, int $rowCount, int $columnCount): array
    {
        return [
            'role' => 'table',
            'aria-label' => $caption,
            'aria-rowcount' => $rowCount,
            'aria-colcount' => $columnCount,
        ];
    }

    /**
     * Generate ARIA attributes for live regions
     */
    public static function getLiveRegionAttributes(string $politeness = 'polite', bool $atomic = false): array
    {
        return [
            'aria-live' => $politeness,
            'aria-atomic' => $atomic ? 'true' : 'false',
        ];
    }

    /**
     * Generate ARIA attributes for form controls
     */
    public static function getFormControlAttributes(string $label, bool $required = false, ?string $describedBy = null, ?string $errorId = null): array
    {
        $attributes = [
            'aria-label' => $label,
        ];

        if ($required) {
            $attributes['aria-required'] = 'true';
        }

        if ($describedBy) {
            $attributes['aria-describedby'] = $describedBy;
        }

        if ($errorId) {
            $attributes['aria-invalid'] = 'true';
            $attributes['aria-describedby'] = ($describedBy ? $describedBy . ' ' : '') . $errorId;
        }

        return $attributes;
    }

    /**
     * Generate ARIA attributes for expandable content
     */
    public static function getExpandableAttributes(bool $expanded = false, ?string $controls = null): array
    {
        $attributes = [
            'aria-expanded' => $expanded ? 'true' : 'false',
        ];

        if ($controls) {
            $attributes['aria-controls'] = $controls;
        }

        return $attributes;
    }

    /**
     * Generate ARIA attributes for progress indicators
     */
    public static function getProgressAttributes(float $value, float $min = 0, float $max = 100, ?string $label = null): array
    {
        $attributes = [
            'role' => 'progressbar',
            'aria-valuenow' => $value,
            'aria-valuemin' => $min,
            'aria-valuemax' => $max,
        ];

        if ($label) {
            $attributes['aria-label'] = $label;
        }

        return $attributes;
    }

    /**
     * Generate skip link for keyboard navigation
     */
    public static function getSkipLinkHtml(string $target = '#main-content', string $text = 'Skip to main content'): string
    {
        return sprintf(
            '<a href="%s" class="skip-link">%s</a>',
            $target,
            htmlspecialchars($text)
        );
    }

    /**
     * Check if color combination meets WCAG AA contrast requirements
     */
    public static function meetsContrastRequirement(string $foreground, string $background, bool $largeText = false): bool
    {
        $requiredRatio = $largeText ? 3.0 : 4.5;
        $actualRatio = self::calculateContrastRatio($foreground, $background);
        
        return $actualRatio >= $requiredRatio;
    }

    /**
     * Calculate contrast ratio between two colors
     */
    private static function calculateContrastRatio(string $color1, string $color2): float
    {
        $luminance1 = self::getLuminance($color1);
        $luminance2 = self::getLuminance($color2);
        
        $lighter = max($luminance1, $luminance2);
        $darker = min($luminance1, $luminance2);
        
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Calculate relative luminance of a color
     */
    private static function getLuminance(string $color): float
    {
        // Convert hex to RGB
        $color = ltrim($color, '#');
        $r = hexdec(substr($color, 0, 2)) / 255;
        $g = hexdec(substr($color, 2, 2)) / 255;
        $b = hexdec(substr($color, 4, 2)) / 255;
        
        // Apply gamma correction
        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        
        // Calculate luminance
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Generate responsive image attributes
     */
    public static function getResponsiveImageAttributes(string $alt, ?string $caption = null): array
    {
        $attributes = [
            'alt' => $alt,
            'loading' => 'lazy',
        ];

        if ($caption) {
            $attributes['aria-describedby'] = 'img-caption-' . md5($caption);
        }

        return $attributes;
    }

    /**
     * Generate landmark attributes for page sections
     */
    public static function getLandmarkAttributes(string $landmark, ?string $label = null): array
    {
        $attributes = [
            'role' => $landmark,
        ];

        if ($label) {
            $attributes['aria-label'] = $label;
        }

        return $attributes;
    }
}