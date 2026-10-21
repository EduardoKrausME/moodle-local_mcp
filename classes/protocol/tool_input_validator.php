<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Validate MCP tool arguments using the same schema exposed in tools/list.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\protocol;

use local_mcp\exception\api_exception;
use stdClass;

/**
 * Validate JSON values without coercing types or exposing input values in errors.
 */
final class tool_input_validator {
    /** Maximum depth of nested inputs and schemas. */
    private const MAX_DEPTH = 32;

    /**
     * Validate a tool's arguments before resolving a Moodle context.
     *
     * @param array $schema Normalized advertised JSON Schema.
     * @param mixed $arguments Original JSON arguments object.
     * @return void
     */
    public static function validate(array $schema, mixed $arguments): void {
        self::check($schema, $arguments, "arguments", 0);
    }

    /**
     * Check one value against its schema, including nested constraints.
     *
     * @param mixed $schema JSON Schema or boolean schema.
     * @param mixed $value JSON value.
     * @param string $path Argument path, never its value.
     * @param int $depth Current nesting depth.
     * @return void
     */
    private static function check(mixed $schema, mixed $value, string $path, int $depth): void {
        if ($depth > self::MAX_DEPTH) {
            self::reject($path, "maximum nesting depth exceeded");
        }
        if ($schema === true) {
            return;
        }
        if ($schema === false) {
            self::reject($path, "value is not allowed");
        }
        $schema = (array)$schema;
        if (isset($schema["type"])) {
            $types = is_array($schema["type"]) ? $schema["type"] : [$schema["type"]];
            $matches = false;
            foreach ($types as $type) {
                if (self::matches_type($value, $type)) {
                    $matches = true;
                    break;
                }
            }
            if (!$matches) {
                self::reject($path, "incorrect JSON type");
            }
        }
        if (isset($schema["enum"]) && !in_array($value, $schema["enum"], true)) {
            self::reject($path, "value is not in the allowed enum");
        }
        if (array_key_exists("const", $schema) && $value !== $schema["const"]) {
            self::reject($path, "value does not match const");
        }

        if (is_string($value)) {
            $length = strlen($value);
            if (isset($schema["minLength"]) && $length < $schema["minLength"]) {
                self::reject($path, "string is shorter than minLength");
            }
            if (isset($schema["maxLength"]) && $length > $schema["maxLength"]) {
                self::reject($path, "string exceeds maxLength");
            }
            if (isset($schema["pattern"])) {
                $pattern = "~" . str_replace("~", "\\~", $schema["pattern"]) . "~u";
                if (@preg_match($pattern, $value) !== 1) {
                    self::reject($path, "string does not match pattern");
                }
            }
        }
        if (is_int($value) || is_float($value)) {
            foreach (["minimum" => "<", "maximum" => ">", "exclusiveMinimum" => "<=",
                "exclusiveMaximum" => ">="] as $key => $operator) {
                if (!isset($schema[$key])) {
                    continue;
                }
                $limit = $schema[$key];
                $invalid = match ($operator) {
                    "<" => $value < $limit,
                    ">" => $value > $limit,
                    "<=" => $value <= $limit,
                    ">=" => $value >= $limit,
                };
                if ($invalid) {
                    self::reject($path, "number violates " . $key);
                }
            }
            if (isset($schema["multipleOf"]) && $schema["multipleOf"] > 0 &&
                    abs($value / $schema["multipleOf"] - round($value / $schema["multipleOf"])) > 1e-9) {
                self::reject($path, "number violates multipleOf");
            }
        }

        if (is_array($value) && array_is_list($value)) {
            $count = count($value);
            if (isset($schema["minItems"]) && $count < $schema["minItems"]) {
                self::reject($path, "array is shorter than minItems");
            }
            if (isset($schema["maxItems"]) && $count > $schema["maxItems"]) {
                self::reject($path, "array exceeds maxItems");
            }
            if (!empty($schema["uniqueItems"])) {
                for ($i = 0; $i < $count; $i++) {
                    for ($j = 0; $j < $i; $j++) {
                        if ($value[$i] === $value[$j]) {
                            self::reject($path, "array has duplicate items");
                        }
                    }
                }
            }
            foreach ($value as $index => $item) {
                $childschema = $schema["prefixItems"][$index] ?? $schema["items"] ?? true;
                self::check($childschema, $item, $path . "[" . $index . "]", $depth + 1);
            }
        }

        if ($value instanceof stdClass || (is_array($value) && !array_is_list($value))) {
            $properties = (array)$value;
            $defined = (array)($schema["properties"] ?? []);
            foreach ($schema["required"] ?? [] as $key) {
                if (!array_key_exists($key, $properties)) {
                    self::reject($path . "." . $key, "required property is missing");
                }
            }
            if (isset($schema["minProperties"]) && count($properties) < $schema["minProperties"]) {
                self::reject($path, "object has fewer than minProperties");
            }
            if (isset($schema["maxProperties"]) && count($properties) > $schema["maxProperties"]) {
                self::reject($path, "object exceeds maxProperties");
            }
            foreach ($properties as $key => $item) {
                $childpath = $path . "." . $key;
                if (isset($schema["propertyNames"])) {
                    self::check($schema["propertyNames"], (string)$key, $childpath, $depth + 1);
                }
                $matchedpattern = false;
                foreach ((array)($schema["patternProperties"] ?? []) as $pattern => $childschema) {
                    $expression = "~" . str_replace("~", "\\~", $pattern) . "~u";
                    if (@preg_match($expression, (string)$key) === 1) {
                        self::check($childschema, $item, $childpath, $depth + 1);
                        $matchedpattern = true;
                    }
                }
                if (array_key_exists($key, $defined)) {
                    self::check($defined[$key], $item, $childpath, $depth + 1);
                } else if (!$matchedpattern && array_key_exists("additionalProperties", $schema)) {
                    self::check($schema["additionalProperties"], $item, $childpath, $depth + 1);
                }
            }
        }

        foreach ($schema["allOf"] ?? [] as $childschema) {
            self::check($childschema, $value, $path, $depth + 1);
        }
        foreach (["anyOf", "oneOf"] as $keyword) {
            if (!isset($schema[$keyword])) {
                continue;
            }
            $matches = 0;
            foreach ($schema[$keyword] as $childschema) {
                try {
                    self::check($childschema, $value, $path, $depth + 1);
                    $matches++;
                } catch (api_exception $e) {
                    if ($e->machinecode !== "invalid_params") {
                        throw $e;
                    }
                }
            }
            if ($matches === 0 || ($keyword === "oneOf" && $matches !== 1)) {
                self::reject($path, "value violates " . $keyword);
            }
        }
        if (isset($schema["not"])) {
            try {
                self::check($schema["not"], $value, $path, $depth + 1);
            } catch (api_exception $e) {
                if ($e->machinecode === "invalid_params") {
                    return;
                }
                throw $e;
            }
            self::reject($path, "value violates not");
        }
    }

    /**
     * Compare JSON types without PHP's implicit string/number conversions.
     *
     * @param mixed $value JSON value.
     * @param mixed $type JSON Schema type.
     * @return bool
     */
    private static function matches_type(mixed $value, mixed $type): bool {
        return match ($type) {
            "object" => $value instanceof stdClass || (is_array($value) && !array_is_list($value)),
            "array" => is_array($value) && array_is_list($value),
            "integer" => is_int($value),
            "number" => is_int($value) || is_float($value),
            "string" => is_string($value),
            "boolean" => is_bool($value),
            "null" => $value === null,
            default => false,
        };
    }

    /**
     * Reject without including untrusted argument values in logs or responses.
     *
     * @param string $path Schema path.
     * @param string $reason Error description.
     * @return never
     */
    private static function reject(string $path, string $reason): never {
        throw new api_exception("invalid_params", 400, "Invalid tool arguments: " . $reason . ".",
            ["path" => $path, "reason" => $reason]);
    }
}
