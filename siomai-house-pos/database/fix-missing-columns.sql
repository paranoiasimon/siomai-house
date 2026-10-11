-- Run this in phpMyAdmin > siomai_house > SQL tab.
-- Adds any columns your older tables are missing. Safe to run more than once (MariaDB/XAMPP).
USE siomai_house;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS customer_id    INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30)  NOT NULL DEFAULT 'Cash',
    ADD COLUMN IF NOT EXISTS amount_paid    DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS cash_received  DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS change_amount  DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS status         VARCHAR(30)  NOT NULL DEFAULT 'Completed';

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS price    DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) NOT NULL DEFAULT 0;

ALTER TABLE stock_logs
    ADD COLUMN IF NOT EXISTS action VARCHAR(30) NOT NULL DEFAULT 'SALE';
