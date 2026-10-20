<?php

namespace Modules\Platform\Ai\Support;

/**
 * OpenAI's structured outputs in strict mode want every property of every
 * object required and nothing more allowed: a property our schema leaves
 * optional becomes "this, or null" there, and strip() takes those nulls out
 * of the answer again, so callers read the same shape from every provider.
 */
final class StrictSchema
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function of(array $schema): array
    {
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::of($schema['items']);
        }
        if (($schema['type'] ?? null) !== 'object' || ! isset($schema['properties']) || ! is_array($schema['properties'])) {
            return $schema;
        }

        $required = (array) ($schema['required'] ?? []);
        $properties = [];
        foreach ($schema['properties'] as $name => $property) {
            $property = self::of((array) $property);
            $properties[$name] = in_array($name, $required, true) ? $property : self::nullable($property);
        }
        $schema['properties'] = $properties === [] ? (object) [] : $properties;
        $schema['required'] = array_keys($properties);
        $schema['additionalProperties'] = false;

        return $schema;
    }

    /**
     * The answer without the nulls strict mode put where a property was optional.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $schema  the prompt's own schema
     * @return array<string, mixed>
     */
    public static function strip(array $data, array $schema): array
    {
        return (array) self::stripValue($data, $schema);
    }

    private static function stripValue(mixed $value, array $schema): mixed
    {
        if (is_array($value) && array_is_list($value) && isset($schema['items']) && is_array($schema['items'])) {
            return array_map(fn ($item) => self::stripValue($item, $schema['items']), $value);
        }
        if (! is_array($value) || ($schema['type'] ?? null) !== 'object' || ! is_array($schema['properties'] ?? null)) {
            return $value;
        }
        $required = (array) ($schema['required'] ?? []);
        foreach ($value as $name => $item) {
            if ($item === null && ! in_array($name, $required, true)) {
                unset($value[$name]);

                continue;
            }
            if (isset($schema['properties'][$name]) && is_array($schema['properties'][$name])) {
                $value[$name] = self::stripValue($item, $schema['properties'][$name]);
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $property
     * @return array<string, mixed>
     */
    private static function nullable(array $property): array
    {
        $type = $property['type'] ?? null;
        if (in_array($type, ['object', 'array'], true) || $type === null) {
            return ['anyOf' => [$property, ['type' => 'null']]];
        }
        $property['type'] = array_values(array_unique([...(array) $type, 'null']));
        if (isset($property['enum']) && is_array($property['enum']) && ! in_array(null, $property['enum'], true)) {
            $property['enum'][] = null;
        }

        return $property;
    }
}
