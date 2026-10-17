-- ============================================================
--  AquaWash Laundry Management System — Database Setup
--  Includes: Tables, Stored Procedures, Functions, Triggers
-- ============================================================

CREATE DATABASE IF NOT EXISTS laundry_db;
USE laundry_db;

-- ============================================================
--  TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS services (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    unit_type VARCHAR(20) DEFAULT 'kg'
);

CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    transaction_date DATETIME NOT NULL,
    estimated_completion_date DATETIME NOT NULL,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('Pending', 'Washing', 'Ironing', 'Ready for Pickup', 'Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS transaction_items (
    transaction_item_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity DECIMAL(8,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payment_methods (
    payment_method_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME NOT NULL,
    status ENUM('Pending','Paid','Failed') DEFAULT 'Pending',
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(payment_method_id)
);

-- Default payment methods
INSERT INTO payment_methods (name) VALUES ('Cash'), ('Transfer Bank'), ('QRIS'), ('Debit Card');


-- ============================================================
--  FUNCTIONS
-- ============================================================

DELIMITER $$

-- Function 1: Format transaction ID menjadi string #TRX-0001
CREATE FUNCTION FormatTransactionID(p_transaction_id INT)
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    RETURN CONCAT('#TRX-', LPAD(p_transaction_id, 4, '0'));
END$$

-- Function 2: Hitung total harga semua item dalam satu transaksi
CREATE FUNCTION CalculateTransactionTotal(p_transaction_id INT)
RETURNS DECIMAL(10,2)
READS SQL DATA
BEGIN
    DECLARE v_total DECIMAL(10,2) DEFAULT 0.00;
    SELECT COALESCE(SUM(subtotal), 0.00)
    INTO v_total
    FROM transaction_items
    WHERE transaction_id = p_transaction_id;
    RETURN v_total;
END$$

-- Function 3: Cek apakah transaksi sudah lunas (Paid)
CREATE FUNCTION IsTransactionPaid(p_transaction_id INT)
RETURNS TINYINT(1)
READS SQL DATA
BEGIN
    DECLARE v_count INT DEFAULT 0;
    SELECT COUNT(*) INTO v_count
    FROM payments
    WHERE transaction_id = p_transaction_id AND status = 'Paid';
    RETURN IF(v_count > 0, 1, 0);
END$$

DELIMITER ;


-- ============================================================
--  STORED PROCEDURES
-- ============================================================

DELIMITER $$

-- Procedure 1: Buat transaksi baru beserta items-nya sekaligus
-- Menerima data transaksi + daftar service dalam format "service_id:qty,service_id:qty"
CREATE PROCEDURE CreateTransaction(
    IN  p_customer_id           INT,
    IN  p_transaction_date      DATETIME,
    IN  p_estimated_completion  DATETIME,
    IN  p_items_csv             TEXT,      -- format: "1:2.5,3:1,2:3"
    OUT p_transaction_id        INT,
    OUT p_total_price           DECIMAL(10,2)
)
BEGIN
    DECLARE v_done       INT DEFAULT 0;
    DECLARE v_item       VARCHAR(50);
    DECLARE v_sid        INT;
    DECLARE v_qty        DECIMAL(8,2);
    DECLARE v_price      DECIMAL(10,2);
    DECLARE v_subtotal   DECIMAL(10,2);
    DECLARE v_total      DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_remaining  TEXT;
    DECLARE v_pos        INT;

    -- Mulai transaksi DB
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_transaction_id = -1;
        SET p_total_price    = 0;
    END;

    START TRANSACTION;

    -- Insert transaksi dengan total 0 dulu (akan diupdate trigger)
    INSERT INTO transactions (customer_id, transaction_date, estimated_completion_date, total_price, status)
    VALUES (p_customer_id, p_transaction_date, p_estimated_completion, 0.00, 'Pending');

    SET p_transaction_id = LAST_INSERT_ID();
    SET v_remaining      = p_items_csv;

    -- Loop parse CSV items "sid:qty,sid:qty"
    WHILE LENGTH(v_remaining) > 0 DO
        SET v_pos = LOCATE(',', v_remaining);
        IF v_pos > 0 THEN
            SET v_item      = SUBSTRING(v_remaining, 1, v_pos - 1);
            SET v_remaining = SUBSTRING(v_remaining, v_pos + 1);
        ELSE
            SET v_item      = v_remaining;
            SET v_remaining = '';
        END IF;

        -- Pisahkan service_id dan qty
        SET v_sid = CAST(SUBSTRING_INDEX(v_item, ':', 1) AS UNSIGNED);
        SET v_qty = CAST(SUBSTRING_INDEX(v_item, ':', -1) AS DECIMAL(8,2));

        -- Ambil harga per unit
        SELECT price_per_unit INTO v_price
        FROM services WHERE service_id = v_sid;

        SET v_subtotal = v_price * v_qty;

        -- Insert item (trigger akan otomatis update total_price di transactions)
        INSERT INTO transaction_items (transaction_id, service_id, quantity, subtotal)
        VALUES (p_transaction_id, v_sid, v_qty, v_subtotal);
    END WHILE;

    -- Ambil total akhir dari tabel (sudah diupdate oleh trigger)
    SELECT total_price INTO p_total_price
    FROM transactions WHERE transaction_id = p_transaction_id;

    COMMIT;
END$$


-- Procedure 2: Rekap pendapatan per periode (harian/bulanan)
CREATE PROCEDURE GetRevenueReport(
    IN p_start_date DATE,
    IN p_end_date   DATE
)
BEGIN
    SELECT
        DATE(t.transaction_date)                    AS tanggal,
        COUNT(DISTINCT t.transaction_id)            AS jumlah_transaksi,
        COUNT(DISTINCT t.customer_id)               AS jumlah_pelanggan,
        SUM(t.total_price)                          AS total_pendapatan,
        AVG(t.total_price)                          AS rata_rata_per_transaksi
    FROM transactions t
    WHERE DATE(t.transaction_date) BETWEEN p_start_date AND p_end_date
    GROUP BY DATE(t.transaction_date)
    ORDER BY tanggal ASC;
END$$


-- Procedure 3: Tambah pembayaran dengan validasi duplikat Paid
CREATE PROCEDURE AddPayment(
    IN  p_transaction_id    INT,
    IN  p_method_id         INT,
    IN  p_amount            DECIMAL(10,2),
    IN  p_payment_date      DATETIME,
    IN  p_status            VARCHAR(10),
    OUT p_result            VARCHAR(100)
)
BEGIN
    DECLARE v_already_paid INT DEFAULT 0;

    SELECT COUNT(*) INTO v_already_paid
    FROM payments
    WHERE transaction_id = p_transaction_id AND status = 'Paid';

    IF v_already_paid > 0 THEN
        SET p_result = 'ERROR: Transaksi ini sudah memiliki pembayaran Paid.';
    ELSE
        INSERT INTO payments (transaction_id, payment_method_id, amount, payment_date, status)
        VALUES (p_transaction_id, p_method_id, p_amount, p_payment_date, p_status);
        SET p_result = 'SUCCESS';
    END IF;
END$$

DELIMITER ;


-- ============================================================
--  TRIGGERS
-- ============================================================

DELIMITER $$

-- Trigger 1: Setelah INSERT transaction_item → update total_price di transactions
CREATE TRIGGER trg_after_item_insert
AFTER INSERT ON transaction_items
FOR EACH ROW
BEGIN
    UPDATE transactions
    SET total_price = CalculateTransactionTotal(NEW.transaction_id)
    WHERE transaction_id = NEW.transaction_id;
END$$

-- Trigger 2: Setelah UPDATE transaction_item → recalculate total_price
CREATE TRIGGER trg_after_item_update
AFTER UPDATE ON transaction_items
FOR EACH ROW
BEGIN
    UPDATE transactions
    SET total_price = CalculateTransactionTotal(NEW.transaction_id)
    WHERE transaction_id = NEW.transaction_id;
END$$

-- Trigger 3: Setelah DELETE transaction_item → recalculate total_price
CREATE TRIGGER trg_after_item_delete
AFTER DELETE ON transaction_items
FOR EACH ROW
BEGIN
    UPDATE transactions
    SET total_price = CalculateTransactionTotal(OLD.transaction_id)
    WHERE transaction_id = OLD.transaction_id;
END$$

-- Trigger 4: Setelah pembayaran berstatus Paid → otomatis update status transaksi jadi Completed
CREATE TRIGGER trg_after_payment_paid
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    IF NEW.status = 'Paid' THEN
        UPDATE transactions
        SET status = 'Completed'
        WHERE transaction_id = NEW.transaction_id
          AND status != 'Completed';
    END IF;
END$$

-- Trigger 5: Setelah UPDATE payments ke Paid → update status transaksi
CREATE TRIGGER trg_after_payment_update
AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
    IF NEW.status = 'Paid' AND OLD.status != 'Paid' THEN
        UPDATE transactions
        SET status = 'Completed'
        WHERE transaction_id = NEW.transaction_id
          AND status != 'Completed';
    END IF;
END$$

DELIMITER ;
