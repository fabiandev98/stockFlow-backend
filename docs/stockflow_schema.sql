CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE material_categories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE materials (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    material_category_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    minimum_stock DECIMAL(12, 2) NOT NULL DEFAULT 0,
    is_perishable BOOLEAN NOT NULL DEFAULT FALSE,
    default_expiration_days INT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT materials_material_category_id_fk
        FOREIGN KEY (material_category_id) REFERENCES material_categories(id)
        ON DELETE SET NULL
);

CREATE TABLE suppliers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE purchases (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    supplier_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    purchase_date DATE NOT NULL,
    total_cost DECIMAL(12, 2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT purchases_supplier_id_fk
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON DELETE SET NULL,
    CONSTRAINT purchases_user_id_fk
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT
);

CREATE TABLE purchase_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    purchase_id BIGINT UNSIGNED NOT NULL,
    material_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(12, 2) NOT NULL,
    unit_cost DECIMAL(12, 4) NOT NULL,
    total_cost DECIMAL(12, 2) NOT NULL,
    expiration_date DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT purchase_items_purchase_id_fk
        FOREIGN KEY (purchase_id) REFERENCES purchases(id)
        ON DELETE CASCADE,
    CONSTRAINT purchase_items_material_id_fk
        FOREIGN KEY (material_id) REFERENCES materials(id)
        ON DELETE RESTRICT
);

CREATE TABLE stock_batches (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    material_id BIGINT UNSIGNED NOT NULL,
    purchase_item_id BIGINT UNSIGNED NULL UNIQUE,
    initial_quantity DECIMAL(12, 2) NOT NULL,
    available_quantity DECIMAL(12, 2) NOT NULL,
    unit_cost DECIMAL(12, 4) NOT NULL,
    received_date DATE NOT NULL,
    expiration_date DATE NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT stock_batches_material_id_fk
        FOREIGN KEY (material_id) REFERENCES materials(id)
        ON DELETE RESTRICT,
    CONSTRAINT stock_batches_purchase_item_id_fk
        FOREIGN KEY (purchase_item_id) REFERENCES purchase_items(id)
        ON DELETE SET NULL
);

CREATE TABLE product_categories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE products (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_category_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    sale_price DECIMAL(12, 2) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT products_product_category_id_fk
        FOREIGN KEY (product_category_id) REFERENCES product_categories(id)
        ON DELETE SET NULL
);

CREATE TABLE product_compositions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    material_id BIGINT UNSIGNED NOT NULL,
    quantity_required DECIMAL(12, 2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT product_compositions_product_id_fk
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE,
    CONSTRAINT product_compositions_material_id_fk
        FOREIGN KEY (material_id) REFERENCES materials(id)
        ON DELETE RESTRICT
);

CREATE TABLE sales (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    sale_date DATE NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT sales_user_id_fk
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT
);

CREATE TABLE sale_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    sale_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(12, 2) NOT NULL,
    total_price DECIMAL(12, 2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT sale_items_sale_id_fk
        FOREIGN KEY (sale_id) REFERENCES sales(id)
        ON DELETE CASCADE,
    CONSTRAINT sale_items_product_id_fk
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT
);

CREATE TABLE stock_movements (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    material_id BIGINT UNSIGNED NOT NULL,
    stock_batch_id BIGINT UNSIGNED NULL,
    sale_item_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    type VARCHAR(50) NOT NULL,
    quantity DECIMAL(12, 2) NOT NULL,
    reason TEXT NULL,
    movement_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT stock_movements_material_id_fk
        FOREIGN KEY (material_id) REFERENCES materials(id)
        ON DELETE RESTRICT,
    CONSTRAINT stock_movements_stock_batch_id_fk
        FOREIGN KEY (stock_batch_id) REFERENCES stock_batches(id)
        ON DELETE SET NULL,
    CONSTRAINT stock_movements_sale_item_id_fk
        FOREIGN KEY (sale_item_id) REFERENCES sale_items(id)
        ON DELETE SET NULL,
    CONSTRAINT stock_movements_user_id_fk
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
);
