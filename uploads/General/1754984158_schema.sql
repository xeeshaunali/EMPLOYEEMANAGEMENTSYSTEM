-- MySQL schema for Court Employee Management System
CREATE DATABASE IF NOT EXISTS court_mgmt DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE court_mgmt;

-- Courts
CREATE TABLE IF NOT EXISTS courts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  district VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Employees table (users)
CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','reader','employee') DEFAULT 'employee',
  court_id INT DEFAULT NULL,
  bps VARCHAR(20) DEFAULT NULL,
  post VARCHAR(100) DEFAULT NULL,
  contact VARCHAR(100) DEFAULT NULL,
  cnic VARCHAR(50) DEFAULT NULL,
  joining_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (court_id) REFERENCES courts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- For backward compatibility we'll create a users view mapping
CREATE OR REPLACE VIEW users AS SELECT * FROM employees;

-- Leaves
CREATE TABLE IF NOT EXISTS leaves (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  leave_type VARCHAR(100) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  remarks TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Files
CREATE TABLE IF NOT EXISTS files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  court_id INT DEFAULT NULL,
  file_path TEXT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (court_id) REFERENCES courts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  status VARCHAR(20) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Transfers
CREATE TABLE IF NOT EXISTS transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  from_court_id INT DEFAULT NULL,
  to_court_id INT DEFAULT NULL,
  date_of_transfer DATE DEFAULT NULL,
  remarks TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample courts and admin user
INSERT INTO courts (name,district) VALUES ('Central Court','Sample District'), ('East Court','Sample District');

INSERT INTO employees (name,username,password_hash,role,court_id,bps,post,contact,cnic,joining_date)
VALUES
('Super Admin','admin', 'REPLACE_HASH', 'admin', 1, 'BPS-21','Administrator','0123456789','12345-6789012-3','2020-01-01'),
('Reader User','reader', 'REPLACE_HASH', 'reader', 1, 'BPS-17','Reader','0123456789','12345-6789012-4','2021-05-01'),
('Sample Employee','emp1', 'REPLACE_HASH', 'employee', 2, 'BPS-05','Clerk','0123456789','12345-6789012-5','2022-03-01');

-- Note: replace REPLACE_HASH with actual password hash for 'password123' created by PHP
-- We'll produce a small helper below to create hashes in README if needed.
