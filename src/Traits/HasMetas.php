<?php

declare(strict_types=1);

namespace AlizHarb\Meta\Traits;

use AlizHarb\Meta\Models\Meta;
use AlizHarb\Meta\Observers\MetaObserver;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasMetas
 *
 * Adds dynamic meta support to an Eloquent model.
 *
 * This trait allows models to handle arbitrary metadata using a polymorphic
 * relationship. It provides methods to get, set, sync, and query meta values
 * with caching support, as well as integration with dynamic property access
 * and legacy Laravel accessors/mutators.
 *
 * @mixin Model
 */
trait HasMetas
{
    /**
     * Meta values queued for persistence.
     *
     * @var array<string, mixed>
     */
    private array $queuedMeta = [];

    /**
     * Cached list of table columns for models using this trait.
     *
     * @var array<string, array<int, string>>|null
     */
    private static ?array $columnCache = null;

    /**
     * Define a polymorphic one-to-many relationship for meta entries.
     *
     * @return MorphMany<Meta>
     */
    public function metas(): MorphMany
    {
        return $this->morphMany(Meta::class, 'metable');
    }

    /**
     * Intercept property access to return meta values when the property
     * does not exist on the base model.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key): mixed
    {
        return $this->shouldDelegateToParent($key)
            ? parent::__get($key)
            : $this->getMeta($key, parent::__get($key));
    }

    /**
     * Intercept property assignment to store values as meta if they do not
     * exist on the base model.
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value): void
    {
        if ($this->hasAttributeMethod($key) || $this->hasOldStyleMutator($key) || $this->hasColumn($key)) {
            parent::__set($key, $value);
        } else {
            $this->queuedMeta[$key] = $value;
        }
    }

    /**
     * Determine if a property is set, including meta values.
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key): bool
    {
        return parent::__isset($key) || $this->getMeta($key) !== null;
    }

    /**
     * Unset a property, deleting meta values if applicable.
     *
     * @param string $key
     */
    public function __unset($key): void
    {
        $this->shouldDelegateToParent($key) ? parent::__unset($key) : $this->forgetMeta($key);
    }

    /**
     * Persist all queued meta values to the database.
     */
    public function persistQueuedMeta(): void
    {
        foreach ($this->queuedMeta as $k => $v) {
            $this->setMeta($k, $v);
        }

        $this->queuedMeta = [];
    }

    /**
     * Set a meta value for the model, optionally scoped by locale.
     *
     * @param string $key
     * @param mixed $value
     * @param string|null $locale
     * @return Meta
     */
    public function setMeta(string $key, mixed $value, ?string $locale = null): Meta
    {
        $meta = $this->metas()->updateOrCreate(
            ['key' => $key],
            ['type' => Meta::detectType($value)]
        );

        $meta->setRealValue($value, $locale);
        $meta->save();
        $this->flushMetaCache($key);

        return $meta;
    }

    /**
     * Retrieve a meta value, with optional default and locale support.
     *
     * @param string $key
     * @param mixed|null $default
     * @param string|null $locale
     * @return mixed
     */
    public function getMeta(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $cacheKey = $this->metaCacheKey($key, $locale);

        return Cache::rememberForever(
            $cacheKey,
            fn() => $this->metas()->where('key', $key)->first()?->getRealValue($locale) ?? $default
        );
    }

    /**
     * Determine if a meta value exists in cache.
     *
     * @param string $key
     * @param string|null $locale
     * @return bool
     */
    public function hasMeta(string $key, ?string $locale = null): bool
    {
        return Cache::has($this->metaCacheKey($key, $locale));
    }

    /**
     * Delete a meta value for the model.
     *
     * @param string $key
     */
    public function forgetMeta(string $key): void
    {
        $this->metas()->where('key', $key)->delete();
        $this->flushMetaCache($key);
    }

    /**
     * Synchronize multiple meta key-value pairs at once.
     *
     * @param array<string, mixed> $pairs
     * @param string|null $locale
     */
    public function syncMeta(array $pairs, ?string $locale = null): void
    {
        foreach ($pairs as $k => $v) {
            $this->setMeta($k, $v, $locale);
        }
    }

    /**
     * Scope a query to models with a specific meta key/value.
     *
     * @param Builder $q
     * @param string $key
     * @param mixed|null $op
     * @param mixed|null $val
     * @return Builder
     */
    #[Scope]
    public function whereMeta(Builder $q, string $key, mixed $op = null, mixed $val = null): Builder
    {
        [$val, $op] = func_num_args() === 2 ? [$op, '='] : [$val, $op];

        return $q->whereHas('metas', fn($q) => $q
            ->where('key', $key)
            ->where('value_' . Meta::detectType($val), $op, $val)
        );
    }

    /**
     * Scope a query to models with meta key in a set of values.
     *
     * @param Builder $q
     * @param string $key
     * @param array<int|string, mixed> $values
     * @return Builder
     */
    #[Scope]
    public function whereMetaIn(Builder $q, string $key, array $values): Builder
    {
        return $q->whereHas('metas', fn($q) => $q
            ->where('key', $key)
            ->whereIn('value_' . Meta::detectType(reset($values)), $values)
        );
    }

    /**
     * Scope a query to models with meta key matching a pattern.
     *
     * @param Builder $query
     * @param string $key
     * @param string $pattern
     * @return Builder
     */
    #[Scope]
    public function whereMetaLike(Builder $query, string $key, string $pattern): Builder
    {
        return $query->whereHas('metas', function ($metaQuery) use ($key, $pattern) {
            $metaQuery->where('key', $key)
                ->where(function ($valueQuery) use ($pattern) {
                    $valueQuery->where('value_string', 'like', $pattern)
                        ->orWhereJsonContains('value_translations', $pattern);
                });
        });
    }

    /**
     * Flush cached meta values for one or all keys.
     *
     * @param string|null $key
     */
    private function flushMetaCache(?string $key = null): void
    {
        $locales = getSupportedLocales();
        $keys = $key ? [$key] : $this->metas()->pluck('key')->unique();

        foreach ($keys as $k) {
            foreach ($locales as $locale) {
                Cache::forget($this->metaCacheKey($k, $locale));
            }
        }
    }

    /**
     * Generate a cache key for a given meta key and locale.
     *
     * @param string $key
     * @param string|null $locale
     * @return string
     */
    private function metaCacheKey(string $key, ?string $locale): string
    {
        return "meta.{$this->getMorphClass()}.{$this->getKey()}.{$key}." . ($locale ?? app()->getLocale());
    }

    /**
     * Determine if property access should be delegated to the parent model.
     *
     * @param string $key
     * @return bool
     */
    private function shouldDelegateToParent(string $key): bool
    {
        $camelKey = Str::camel($key);

        return $this->hasColumn($key)
            || $this->hasAttributeMethod($key)
            || $this->hasAttributeMethod($camelKey)
            || $this->hasOldStyleAccessor($key)
            || $this->hasOldStyleAccessor($camelKey)
            || $this->hasOldStyleMutator($key)
            || $this->hasOldStyleMutator($camelKey)
            || $this->relationLoaded($key)
            || method_exists($this, $key)
            || method_exists($this, $camelKey);
    }

    /**
     * Check if a database column exists on the model.
     *
     * @param string $key
     * @return bool
     */
    private function hasColumn(string $key): bool
    {
        $table = $this->getTable();

        if (!isset(self::$columnCache[$table])) {
            self::$columnCache[$table] = $this->getConnection()->getSchemaBuilder()->getColumnListing($table);
        }

        return in_array($key, self::$columnCache[$table], true);
    }

    /**
     * Determine if a method returns an Attribute instance (new style accessor).
     *
     * @param string $key
     * @return bool
     */
    private function hasAttributeMethod(string $key): bool
    {
        if (!method_exists($this, $key)) return false;

        $reflection = new \ReflectionMethod($this, $key);

        return $reflection->getReturnType() instanceof \ReflectionNamedType
            && $reflection->getReturnType()?->getName() === Attribute::class;
    }

    /**
     * Check for legacy accessor method.
     *
     * @param string $key
     * @return bool
     */
    private function hasOldStyleAccessor(string $key): bool
    {
        return method_exists($this, 'get' . Str::studly($key) . 'Attribute');
    }

    /**
     * Check for legacy mutator method.
     *
     * @param string $key
     * @return bool
     */
    private function hasOldStyleMutator(string $key): bool
    {
        return method_exists($this, 'set' . Str::studly($key) . 'Attribute');
    }

    /**
     * Boot the trait and attach observer to handle meta events.
     */
    protected static function bootHasMetas(): void
    {
        static::observe(MetaObserver::class);
    }
}
