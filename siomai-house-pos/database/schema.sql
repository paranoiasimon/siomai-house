-- Siomai House POS - run this in phpMyAdmin (Import tab) or: mysql -u root < schema.sql
CREATE DATABASE IF NOT EXISTS siomai_house CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siomai_house;

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (id, role_name) VALUES
    (1, 'Admin'), (2, 'Owner'), (3, 'Cashier'), (4, 'Staff');

-- Client (buyer) accounts have their own table below; `users` is for Admin / Owner / Cashier / Staff.


CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    status ENUM('Success','Failed') NOT NULL,
    ip_address VARCHAR(45) NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    user_id INT UNSIGNED NULL,
    client_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL UNIQUE,
    category VARCHAR(100) NOT NULL DEFAULT '',
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    status ENUM('Available','Unavailable') NOT NULL DEFAULT 'Available',
    image VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    client_id INT UNSIGNED NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','GCash','Card') NOT NULL DEFAULT 'Cash',
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    cash_received DECIMAL(10,2) NOT NULL DEFAULT 0,
    change_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'Completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    action VARCHAR(30) NOT NULL,
    quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Sample products (images are in backend/images/it_1.jpg ... it_9.jpg). Edit names/prices freely.
INSERT IGNORE INTO products (product_name, category, price, stock, status, image) VALUES
    ('Pork Siomai',        'Siomai',    50.00, 100, 'Available', 'it_1.jpg'),
    ('Chicken Siomai',     'Siomai',    45.00, 100, 'Available', 'it_2.jpg'),
    ('Beef Siomai',        'Siomai',    55.00, 100, 'Available', 'it_3.jpg'),
    ('Lumpia',             'Snack',     55.00, 100, 'Available', 'it_4.jpg'),
    ('Siomai Rice',        'Rice Meal', 85.00, 100, 'Available', 'it_5.jpg'),
    ('Buchi',              'Dessert',   20.00, 100, 'Available', 'it_6.jpg'),
    ('Halo-Halo',          'Dessert',  100.00, 100, 'Available', 'it_7.jpg'),
    ('Iced Drinks',        'Drinks',   139.00, 100, 'Available', 'it_8.jpg'),
    ('Siomai House Combo', 'Combo',    120.00, 100, 'Available', 'it_9.jpg');
