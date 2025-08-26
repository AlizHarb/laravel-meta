# Laravel Meta

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Latest Version on
Packagist](https://img.shields.io/packagist/v/alizharb/laravel-meta.svg?style=flat-square)](https://packagist.org/packages/alizharb/laravel-meta)
[![Total
Downloads](https://img.shields.io/packagist/dt/alizharb/laravel-meta.svg?style=flat-square)](https://packagist.org/packages/alizharb/laravel-meta)

A flexible and powerful **Meta system for Laravel**.\
Easily attach dynamic key-value metadata to any Eloquent model with
support for multiple data types, translations, caching, and query
scopes.

---

## Features

- 🔑 Attach dynamic meta key-value pairs to any model
- 🌍 Built-in support for translations
- 🗄️ Automatic type detection (`string`, `boolean`, `number`, `date`,
  `json`)
- ⚡ Cached retrieval for performance
- 🔎 Query scopes (`whereMeta`, `whereMetaIn`, `whereMetaLike`)
- 🎛️ Simple configuration and migration publishing

---

## Installation

Install the package via Composer:

```bash
composer require alizharb/laravel-meta
```

---

## Publishing Config & Migration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=meta-config
```

This will create `config/meta.php` with supported locales.

Publish the migration:

```bash
php artisan vendor:publish --tag=meta-migrations
```

Then run the migration:

```bash
php artisan migrate
```

---

## Usage

### 1. Add the `HasMetas` trait to your model

```php
use Illuminate\Database\Eloquent\Model;
use AlizHarb\Meta\Traits\HasMetas;

class Post extends Model
{
    use HasMetas;
}
```

### 2. Setting Meta

```php
$post = Post::find(1);

// Direct property assignment
$post->subtitle = "A flexible meta system";
$post->save();

// Using setMeta
$post->setMeta('views', 123);
$post->setMeta('is_featured', true);
$post->setMeta('published_at', now());
```

### 3. Getting Meta

```php
echo $post->subtitle; // "A flexible meta system"
echo $post->getMeta('views'); // 123
```

With default value:

```php
$post->getMeta('non_existing_key', 'default_value');
```

### 4. Translatable Meta

```php
$post->setMeta('title', [
    'en' => 'Hello World',
    'ar' => 'مرحبا بالعالم',
]);

echo $post->getMeta('title', null, 'en'); // Hello World
echo $post->getMeta('title', null, 'ar'); // مرحبا بالعالم
```

### 5. Querying by Meta

```php
// Exact match
Post::whereMeta('views', '>=', 100)->get();

// In array
Post::whereMetaIn('status', ['draft', 'published'])->get();

// Like search
Post::whereMetaLike('subtitle', '%flexible%')->get();
```

### 6. Forgetting Meta

```php
$post->forgetMeta('subtitle');
```

### 7. Syncing Meta

```php
$post->syncMeta([
    'views' => 999,
    'is_featured' => false,
]);
```

---

## Configuration

By default, the package comes with:

```php
return [
    'supported_locales' => [
        'ar',
        'en',
    ],
];
```

You can modify the locales in `config/meta.php`.

---

## Database Schema

The migration creates a `metas` table with the following structure:

- `id`
- `metable_type` & `metable_id` (morphs)
- `key`
- `type`
- `value_string`
- `value_translations`
- `value_json`
- `value_decimal`
- `value_boolean`
- `value_datetime`
- timestamps

---

## Caching

- Meta values are cached forever for performance.
- Cache is automatically flushed when values are updated or deleted.

---

## Observers

The package registers a `MetaObserver` automatically to keep cache and
data in sync.

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more
information.

---

## Author

Developed and maintained by **Ali Harb**\
📦 [alizharb/laravel-meta](https://github.com/alizharb/laravel-meta)
