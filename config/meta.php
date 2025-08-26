<?php

/**
 * Configuration for supported locales in the application.
 *
 * This configuration file defines the locales that are supported for 
 * translatable meta values throughout the application. 
 * 
 * The array returned contains the following keys:
 * 
 * @return array{
 *     /**
 *      * List of locales supported by the application.
 *      *
 *      * These are ISO 639-1 codes representing languages.
 *      * Example: 'en' for English, 'ar' for Arabic.
 *      *
 *      * @var string[]
 *      */
 *     supported_locales: string[]
 * }
 */
return [
    // Supported locales for translatable meta values
    'supported_locales' => [
        'ar', // Arabic
        'en', // English
    ],
];
