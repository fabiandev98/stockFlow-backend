<?php

namespace App\Enums;

enum DenebPermission: string
{
    case USERS_CREATE = 'users-create';
    case USERS_READ = 'users-read';
    case USERS_UPDATE = 'users-update';
    case USERS_DELETE = 'users-delete';

    case ROLES_CREATE = 'roles-create';
    case ROLES_READ = 'roles-read';
    case ROLES_UPDATE = 'roles-update';
    case ROLES_DELETE = 'roles-delete';

    case MATERIAL_CATEGORIES_CREATE = 'material-categories-create';
    case MATERIAL_CATEGORIES_READ = 'material-categories-read';
    case MATERIAL_CATEGORIES_UPDATE = 'material-categories-update';
    case MATERIAL_CATEGORIES_DELETE = 'material-categories-delete';

    case MATERIALS_CREATE = 'materials-create';
    case MATERIALS_READ = 'materials-read';
    case MATERIALS_UPDATE = 'materials-update';
    case MATERIALS_DELETE = 'materials-delete';

    case PRODUCT_CATEGORIES_CREATE = 'product-categories-create';
    case PRODUCT_CATEGORIES_READ = 'product-categories-read';
    case PRODUCT_CATEGORIES_UPDATE = 'product-categories-update';
    case PRODUCT_CATEGORIES_DELETE = 'product-categories-delete';

    case PRODUCTS_CREATE = 'products-create';
    case PRODUCTS_READ = 'products-read';
    case PRODUCTS_UPDATE = 'products-update';
    case PRODUCTS_DELETE = 'products-delete';

    case SUPPLIERS_CREATE = 'suppliers-create';
    case SUPPLIERS_READ = 'suppliers-read';
    case SUPPLIERS_UPDATE = 'suppliers-update';
    case SUPPLIERS_DELETE = 'suppliers-delete';

    case PURCHASES_CREATE = 'purchases-create';
    case PURCHASES_READ = 'purchases-read';
    case PURCHASES_UPDATE = 'purchases-update';
    case PURCHASES_DELETE = 'purchases-delete';

    case SALES_CREATE = 'sales-create';
    case SALES_READ = 'sales-read';

    case STOCK_BATCHES_READ = 'stock-batches-read';

    case STOCK_MOVEMENTS_CREATE = 'stock-movements-create';
    case STOCK_MOVEMENTS_READ = 'stock-movements-read';

    case INVENTORY_READ = 'inventory-read';

    case MANAGE_API_KEYS_CREATE = 'manage-api-keys-create';
    case MANAGE_API_KEYS_READ = 'manage-api-keys-read';
    case MANAGE_API_KEYS_UPDATE = 'manage-api-keys-update';
    case MANAGE_API_KEYS_DELETE = 'manage-api-keys-delete';

    case API_KEYS_CREATE = 'api-keys-create';
    case API_KEYS_READ = 'api-keys-read';
    case API_KEYS_UPDATE = 'api-keys-update';
    case API_KEYS_DELETE = 'api-keys-delete';

    /**
     * Place here any other permission
     */
}
