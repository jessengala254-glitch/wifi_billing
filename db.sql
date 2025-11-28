-- leokonnect schema
CREATE DATABASE IF NOT EXISTS leokonnect DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE leokonnect;

-- users table
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('user','admin') DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- plans table
CREATE TABLE plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  duration_minutes INT NOT NULL, -- duration in minutes
  description TEXT,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- payments table
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_id INT NOT NULL,
  mpesa_receipt VARCHAR(100),
  amount DECIMAL(10,2) NOT NULL,
  phone VARCHAR(20),
  status ENUM('pending','success','failed') DEFAULT 'pending',
  transaction_time DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
);

-- sessions table: active internet grants
CREATE TABLE sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_id INT NOT NULL,
  ip VARCHAR(45),
  mac VARCHAR(50),
  started_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
);

CREATE TABLE login_sessions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(50) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'active',
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Optional: logs for admin visibility
CREATE TABLE logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(50),
  message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample plans
INSERT INTO plans (title, price, duration_minutes, description) VALUES
('1 Hour', 10.00, 60, 'Unlimited access for 1 hour'),
('1 Day', 50.00, 1440, 'Unlimited access for 1 day'),
('1 Week', 300.00, 10080, 'Unlimited access for 1 week');
