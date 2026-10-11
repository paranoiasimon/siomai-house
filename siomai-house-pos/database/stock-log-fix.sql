-- OPTIONAL. Run in phpMyAdmin > siomai_house > SQL tab ONLY if Inventory > Recent Stock Movements
-- stays empty after you adjust stock (it means stock_logs.action only accepts fixed values).
USE siomai_house;
ALTER TABLE stock_logs MODIFY action VARCHAR(30) NOT NULL;
