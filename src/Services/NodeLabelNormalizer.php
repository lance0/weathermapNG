<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

/**
 * Normalizes node labels across all write paths so a label persisted via
 * the single-node endpoints (create/update/store) is identical to the same
 * label persisted via the bulk SaveMapRequest path.
 *
 * Normalization is strip_tags(trim()) only. HTML-entity escaping is left to
 * render time (escapeHtml/textContent in the editor and embed views) so the
 * stored label is the plain text the operator typed, not a view-specific
 * encoding.
 */
class NodeLabelNormalizer
{
    /**
     * Strip HTML tags and trim whitespace. Returns the normalized label,
     * which may be empty if the input held only markup (e.g. "<b></b>").
     * Use normalizeOrThrow() when the caller requires a non-empty result.
     */
    public static function normalize(?string $label): string
    {
        return strip_tags(trim((string) $label));
    }

    /**
     * Normalize and enforce a non-empty result. Use this for write paths
     * where an empty label is invalid (the single-node create/update/store
     * endpoints). Controllers map the thrown exception to a 422.
     *
     * @throws \InvalidArgumentException when the label is empty after stripping.
     */
    public static function normalizeOrThrow(?string $label): string
    {
        $normalized = self::normalize($label);
        if ($normalized === '') {
            throw new \InvalidArgumentException(
                'Node label must not be empty after removing HTML tags.'
            );
        }
        return $normalized;
    }
}
