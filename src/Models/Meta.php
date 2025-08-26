<?php

declare(strict_types=1);

namespace AlizHarb\Meta\Models;

use AlizHarb\Meta\Observers\MetaObserver;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

/**
 * Class Model Meta
 *
 * Represents a flexible meta value for any Eloquent model. Supports multiple
 * data types including strings, numbers, booleans, JSON arrays, dates, and
 * translatable values. Designed to work with the HasMetas trait.
 *
 * @property int $id
 * @property string $key The meta key identifier
 * @property string $type The type of the meta value (boolean, number, string, json, date)
 * @property string|null $value_string The stored string value
 * @property array|null $value_translations Translations for string values
 * @property array|null $value_json JSON values stored as array
 * @property float|int|null $value_decimal Numeric value (integer or decimal)
 * @property bool|null $value_boolean Boolean value
 * @property \Illuminate\Support\Carbon|null $value_datetime Datetime value
 * @property-read MorphTo $metable The polymorphic relationship owner
 */
#[ObservedBy(MetaObserver::class)]
class Meta extends Model
{
    use HasTranslations;

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'type',
        'value_string',
        'value_translations',
        'value_json',
        'value_decimal',
        'value_boolean',
        'value_datetime',
    ];

    /**
     * Attribute casting definitions.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value_json' => 'array',
        'value_boolean' => 'boolean',
        'value_datetime' => 'datetime',
        'value_decimal' => 'decimal:30',
    ];

    /**
     * Translatable attributes for Spatie package.
     *
     * @var array<int, string>
     */
    public array $translatable = ['value_translations'];

    /**
     * Polymorphic relationship to the owning model.
     *
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, self>
     */
    public function metable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Detect the type of a given value.
     *
     * Used to determine which field should store the value and how it should
     * be retrieved.
     *
     * @param mixed $value
     * @return string Returns 'boolean', 'number', 'date', 'string', or 'json'
     */
    public static function detectType(mixed $value): string
    {
        if (is_array($value)) {
            $locales = getSupportedLocales();
            if (!array_diff(array_keys($value), $locales)) {
                return 'string';
            }
            return 'json';
        }

        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            $value instanceof \DateTimeInterface => 'date',
            default => 'string',
        };
    }

    /**
     * Get the actual value according to its type and optional locale.
     *
     * @param string|null $locale The locale for translatable values
     * @return mixed Returns value as boolean, int, float, string, array, or \DateTimeInterface
     */
    public function getRealValue(?string $locale = null): mixed
    {
        return match ($this->type) {
            'boolean' => $this->value_boolean,
            'date' => $this->value_datetime,
            'number' => $this->value_decimal,
            'json' => $this->value_json,
            default => $this->getTranslation('value_translations', $locale ?? app()->getLocale())
                ?? $this->value_string,
        };
    }

    /**
     * Set the real value and update the type accordingly.
     *
     * Supports automatic detection of type, locale handling for translatable
     * strings, and proper assignment for JSON, numbers, booleans, and dates.
     *
     * @param mixed $value The value to store
     * @param string|null $locale Optional locale for translatable strings
     */
    public function setRealValue(mixed $value, ?string $locale = null): void
    {
        $supportedLocales = getSupportedLocales();

        if (is_array($value) && !array_diff(array_keys($value), $supportedLocales)) {
            $this->type = 'string';
            foreach ($value as $lang => $translation) {
                $this->setTranslation('value_translations', $lang, $translation ?? '');
            }
            return;
        }

        $this->type = static::detectType($value);

        match ($this->type) {
            'boolean' => $this->value_boolean = (bool)$value,
            'date' => $this->value_datetime = $value instanceof \DateTimeInterface ? $value : new \DateTime($value),
            'number' => $this->value_decimal = is_string($value) && str_contains($value, '.') ? (float)$value : (int)$value,
            'json' => $this->value_json = $value,
            default => $this->setTranslation('value_translations', $locale ?? app()->getLocale(), (string)$value),
        };
    }
}
