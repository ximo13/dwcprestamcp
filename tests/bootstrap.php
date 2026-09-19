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

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

if (!defined('_PS_CACHE_DIR_')) {
    define('_PS_CACHE_DIR_', sys_get_temp_dir() . '/');
}

if (!function_exists('pSQL')) {
    function pSQL(string $string, bool $htmlOK = false, bool $bqSQL = false): string
    {
        return $string;
    }
}

if (!class_exists('Tools', false)) {
    class Tools
    {
        public static function getHttpHost(bool $http = false, bool $entities = false): string
        {
            return '';
        }
    }
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
        public int $id = 0;

        public string $iso_code = '';

        /**
         * @return array<int, array<string, mixed>>
         */
        public static function getLanguages(bool $active = true): array
        {
            return [];
        }
    }
}

if (!class_exists('Validate', false)) {
    class Validate
    {
        public static function isLoadedObject(mixed $object): bool
        {
            return true;
        }
    }
}

if (!class_exists('Product', false)) {
    class Product
    {
        /** @var array<int, string> */
        public array $name = [];

        public float $price = 0.0;

        public int|bool $active = 0;

        public string $reference = '';

        public float $weight = 0.0;

        public int|bool $on_sale = 0;

        public string $ean13 = '';

        public string $upc = '';

        public string $isbn = '';

        public string $mpn = '';

        public string $visibility = 'both';

        public string $condition = 'new';

        public int $id_manufacturer = 0;

        public int $id_supplier = 0;

        public int $id_category_default = 0;

        public float $wholesale_price = 0.0;

        public string $date_add = '';

        public string $date_upd = '';

        /** @var array<int, string> */
        public array $description = [];

        /** @var array<int, string> */
        public array $description_short = [];

        /** @var array<int, string> */
        public array $meta_title = [];

        /** @var array<int, string> */
        public array $meta_description = [];

        public function __construct(?int $id = null, bool $full = false, ?int $idLang = null)
        {
        }

        public function save(): bool
        {
            return true;
        }

        /**
         * @param mixed $specificPriceOutput
         */
        public static function getPriceStatic(
            int $idProduct,
            bool $usetax = true,
            ?int $idProductAttribute = null,
            int $decimals = 6,
            ?int $divisor = null,
            bool $onlyReduc = false,
            bool $usereduc = true,
            int $quantity = 1,
            bool $forceAssociatedTax = false,
            ?int $idCustomer = null,
            ?int $idCart = null,
            ?int $idAddress = null,
            &$specificPriceOutput = null,
            bool $withEcotax = true,
            bool $useGroupReduction = true
        ): float {
            return 0.0;
        }

        public function getTaxesRate(): float
        {
            return 0.0;
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public static function getProductCategoriesFull(int $idProduct, ?int $idLang = null): array
        {
            return [];
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public static function getFrontFeaturesStatic(int $idLang, int $idProduct): array
        {
            return [];
        }
    }
}

if (!class_exists('Image', false)) {
    class Image
    {
        /**
         * @return array<int, array<string, mixed>>
         */
        public static function getImages(int $idLang, int $idProduct): array
        {
            return [];
        }
    }
}

if (!class_exists('Manufacturer', false)) {
    class Manufacturer
    {
        public static function getNameById(int $id): string
        {
            return '';
        }
    }
}

if (!class_exists('Supplier', false)) {
    class Supplier
    {
        public static function getNameById(int $id): string
        {
            return '';
        }
    }
}

if (!class_exists('Link', false)) {
    class Link
    {
        public function getImageLink(string $name, string $ids): string
        {
            return '';
        }
    }
}

if (!class_exists('StockAvailable', false)) {
    class StockAvailable
    {
        public static function getQuantityAvailableByProduct(int $idProduct, int $idProductAttribute = 0, ?int $idShop = null): int
        {
            return 0;
        }

        public static function setQuantity(int $idProduct, int $idProductAttribute, int $quantity, ?int $idShop = null, bool $addMovement = true): void
        {
        }
    }
}

if (!class_exists('Shop', false)) {
    class Shop
    {
        public int $id = 0;
    }
}

if (!class_exists('Db', false)) {
    class Db
    {
        public static function getInstance(): Db
        {
            return new self();
        }

        /**
         * @return array<int, array<string, string|null>>|false
         */
        public function executeS(string $sql)
        {
            return [];
        }

        /**
         * @return string|false
         */
        public function getValue(string $sql)
        {
            return false;
        }
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

        public ?Shop $shop = null;

        public ?Link $link = null;

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
