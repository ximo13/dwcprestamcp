<?php
/**
 * PHPStan bootstrap.
 *
 * Defines the minimal PrestaShop runtime surface used by this module so that
 * static analysis can run standalone (outside a PrestaShop installation).
 * These symbols are NEVER declared when the module runs inside PrestaShop,
 * because PrestaShop defines them first; the guards below make sure of that.
 */

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '9.0.0');
}

if (!class_exists('Configuration', false)) {
    class Configuration
    {
        /** @return mixed */
        public static function get(string $key)
        {
            return null;
        }
    }
}

if (!class_exists('Language', false)) {
    class Language
    {
        public string $iso_code = '';
    }
}

if (!class_exists('Currency', false)) {
    class Currency
    {
        public string $iso_code = '';
    }
}

if (!class_exists('Context', false)) {
    class Context
    {
        public ?Language $language = null;

        public ?Currency $currency = null;

        public static function getContext(): ?Context
        {
            return null;
        }
    }
}

if (!class_exists('Module', false)) {
    class Module
    {
        public static function isInstalled(string $name): bool
        {
            return false;
        }

        public static function isEnabled(string $name): bool
        {
            return false;
        }
    }
}
