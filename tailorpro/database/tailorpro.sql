CREATE DATABASE IF NOT EXISTS tailorpro
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE tailorpro;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    business_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    address VARCHAR(255) DEFAULT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tailor_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    gender ENUM('Male','Female') NOT NULL,
    address VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_customers_tailor
        FOREIGN KEY (tailor_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_customers_tailor (tailor_id),
    INDEX idx_customers_name (name),
    INDEX idx_customers_phone (phone)
) ENGINE=InnoDB;

CREATE TABLE measurements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    neck DECIMAL(6,2) DEFAULT NULL,
    shoulder DECIMAL(6,2) DEFAULT NULL,
    chest DECIMAL(6,2) DEFAULT NULL,
    bust DECIMAL(6,2) DEFAULT NULL,
    waist DECIMAL(6,2) DEFAULT NULL,
    hip DECIMAL(6,2) DEFAULT NULL,
    sleeve DECIMAL(6,2) DEFAULT NULL,
    biceps DECIMAL(6,2) DEFAULT NULL,
    wrist DECIMAL(6,2) DEFAULT NULL,
    armhole DECIMAL(6,2) DEFAULT NULL,
    blouse_length DECIMAL(6,2) DEFAULT NULL,
    gown_length DECIMAL(6,2) DEFAULT NULL,
    trouser_length DECIMAL(6,2) DEFAULT NULL,
    skirt_length DECIMAL(6,2) DEFAULT NULL,
    thigh DECIMAL(6,2) DEFAULT NULL,
    knee DECIMAL(6,2) DEFAULT NULL,
    bottom DECIMAL(6,2) DEFAULT NULL,
    custom_measurements TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_measurements_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_measurements_customer (customer_id)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    tailor_id INT UNSIGNED NOT NULL,
    clothing_type VARCHAR(100) NOT NULL,
    design VARCHAR(255) DEFAULT NULL,
    gender ENUM('Male','Female') DEFAULT NULL,
    fabric_type VARCHAR(100) DEFAULT NULL,
    fabric_quantity DECIMAL(10,2) DEFAULT NULL,
    fabric_unit VARCHAR(30) DEFAULT 'yards',
    color VARCHAR(50) DEFAULT NULL,
    special_instructions TEXT DEFAULT NULL,
    date_submitted DATE NOT NULL,
    collection_date DATE NOT NULL,
    actual_collection_date DATE DEFAULT NULL,
    amount_charged DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('Pending','In Progress','Ready','Collected','Cancelled') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_orders_tailor
        FOREIGN KEY (tailor_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_orders_tailor (tailor_id),
    INDEX idx_orders_customer (customer_id),
    INDEX idx_orders_collection (collection_date),
    INDEX idx_orders_status (status)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('Cash','Bank Transfer','POS','Other') NOT NULL DEFAULT 'Cash',
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE,
    INDEX idx_payments_order (order_id)
) ENGINE=InnoDB;

CREATE TABLE reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    reminder_date DATE NOT NULL,
    reminder_type ENUM('Before Collection','Due Today','Overdue') NOT NULL DEFAULT 'Before Collection',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reminders_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE,
    INDEX idx_reminders_date (reminder_date)
) ENGINE=InnoDB;
