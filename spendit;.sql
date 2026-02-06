CREATE DATABASE spendit;
USE spendit;
CREATE TABLE salaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(10,2) NOT NULL,
    pay_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50) DEFAULT 'General',
    expense_date DATE NOT NULL,
    salary_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE months (
    id INT AUTO_INCREMENT PRIMARY KEY,
    month INT NOT NULL,
    year INT NOT NULL,
    total_spent DECIMAL(10,2) DEFAULT 0.00,
    total_salary DECIMAL(10,2) DEFAULT 0.00,
    balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_month (month, year)
);
-- Example salaries for September 2025
INSERT INTO salaries (amount, pay_date) VALUES
(50000.00, '2025-09-07'),
(50000.00, '2025-09-22');
INSERT INTO expenses (item_name, price, category, expense_date)
VALUES ('Grocery Shopping', 4500.00, 'Food', '2025-09-08');
ALTER TABLE expenses ADD COLUMN salary_id INT NULL;
ALTER TABLE expenses ADD COLUMN subcategory VARCHAR(100) AFTER category;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

  ALTER TABLE users
ADD remember_token VARCHAR(64) NULL;
);
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
