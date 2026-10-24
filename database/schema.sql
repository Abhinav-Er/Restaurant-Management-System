-- Pawan Punjabi Chinese Restaurant: database schema
-- Import this file once in phpMyAdmin (steps below the code).

CREATE DATABASE IF NOT EXISTS pawan_restaurant
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pawan_restaurant;

-- ---------- Customers ----------
CREATE TABLE customer (
  customer_id          INT AUTO_INCREMENT PRIMARY KEY,
  name                 VARCHAR(100) NOT NULL,
  email                VARCHAR(150) NOT NULL UNIQUE,
  phone                CHAR(10)     NOT NULL,
  password_hash        VARCHAR(255) NOT NULL,
  diet                 ENUM('none','veg','nonveg')      NOT NULL DEFAULT 'none',
  spice                ENUM('mild','medium','hot')      NOT NULL DEFAULT 'medium',
  preferred_order_type ENUM('dinein','takeaway')        NOT NULL DEFAULT 'dinein',
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Staff (admin, manager, kitchen) ----------
CREATE TABLE admin_manager (
  admin_id      INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','manager','kitchen') NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Menu ----------
CREATE TABLE menu_item (
  item_id        INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100) NOT NULL,
  description    VARCHAR(255) NOT NULL DEFAULT '',
  image          VARCHAR(255) NULL,
  category       VARCHAR(50)  NOT NULL,
  price          DECIMAL(8,2) NOT NULL CHECK (price >= 0),
  stock_quantity INT          NOT NULL DEFAULT 0 CHECK (stock_quantity >= 0),
  is_available   TINYINT(1)   NOT NULL DEFAULT 1,
  is_veg         TINYINT(1)   NOT NULL DEFAULT 1,
  is_featured    TINYINT(1)   NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ---------- Coupons ----------
CREATE TABLE coupon (
  coupon_id        INT AUTO_INCREMENT PRIMARY KEY,
  code             VARCHAR(30)  NOT NULL UNIQUE,
  discount_type    ENUM('percent','flat') NOT NULL,
  discount_value   DECIMAL(8,2) NOT NULL CHECK (discount_value > 0),
  min_order_amount DECIMAL(8,2) NOT NULL DEFAULT 0,
  expiry_date      DATE NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------- Group ordering ----------
CREATE TABLE group_order (
  group_order_id INT AUTO_INCREMENT PRIMARY KEY,
  room_code      VARCHAR(8) NOT NULL UNIQUE,
  creator_id     INT NOT NULL,
  status         ENUM('open','closed','ordered') NOT NULL DEFAULT 'open',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (creator_id) REFERENCES customer(customer_id)
) ENGINE=InnoDB;

CREATE TABLE group_order_item (
  group_item_id  INT AUTO_INCREMENT PRIMARY KEY,
  group_order_id INT NOT NULL,
  customer_id    INT NOT NULL,
  item_id        INT NOT NULL,
  quantity       INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
  FOREIGN KEY (group_order_id) REFERENCES group_order(group_order_id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id)    REFERENCES customer(customer_id),
  FOREIGN KEY (item_id)        REFERENCES menu_item(item_id)
) ENGINE=InnoDB;

-- ---------- Orders ----------
-- Named "orders" because ORDER is a reserved word in SQL.
CREATE TABLE orders (
  order_id        INT AUTO_INCREMENT PRIMARY KEY,
  customer_id     INT NOT NULL,
  contact_name    VARCHAR(100) NOT NULL,
  contact_phone   CHAR(10)     NOT NULL,
  order_type      ENUM('dinein','takeaway') NOT NULL,
  table_no        VARCHAR(10)  NULL,
  notes           VARCHAR(200) NULL,
  scheduled_time  DATETIME     NULL,
  status          ENUM('placed','paid','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'placed',
  coupon_id       INT NULL,
  discount_amount DECIMAL(8,2)  NOT NULL DEFAULT 0,
  total_amount    DECIMAL(10,2) NOT NULL,
  group_order_id  INT NULL,
  order_date      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id)    REFERENCES customer(customer_id),
  FOREIGN KEY (coupon_id)      REFERENCES coupon(coupon_id),
  FOREIGN KEY (group_order_id) REFERENCES group_order(group_order_id),
  INDEX idx_orders_customer (customer_id, order_date),
  INDEX idx_orders_status (status)
) ENGINE=InnoDB;

CREATE TABLE order_item (
  order_item_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT NOT NULL,
  item_id       INT NOT NULL,
  quantity      INT NOT NULL CHECK (quantity > 0),
  unit_price    DECIMAL(8,2) NOT NULL,   -- price at the time of ordering, so old bills never change
  subtotal      DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY (item_id)  REFERENCES menu_item(item_id)
) ENGINE=InnoDB;

CREATE TABLE payment (
  payment_id      INT AUTO_INCREMENT PRIMARY KEY,
  order_id        INT NOT NULL UNIQUE,   -- UNIQUE makes it one payment per order
  amount          DECIMAL(10,2) NOT NULL,
  payment_method  ENUM('upi','card') NOT NULL,
  payment_status  ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  transaction_ref VARCHAR(100) NULL,
  paid_at         DATETIME NULL,
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Favorites, reviews, notifications ----------
CREATE TABLE favorite (
  favorite_id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  item_id     INT NOT NULL,
  UNIQUE KEY uq_favorite (customer_id, item_id),
  FOREIGN KEY (customer_id) REFERENCES customer(customer_id) ON DELETE CASCADE,
  FOREIGN KEY (item_id)     REFERENCES menu_item(item_id)    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE review (
  review_id   INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  item_id     INT NOT NULL,
  order_id    INT NOT NULL,              -- the delivered order that makes the review "verified"
  rating      TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  review_text VARCHAR(300) NOT NULL DEFAULT '',
  is_hidden   TINYINT(1) NOT NULL DEFAULT 0,   -- set by an admin in review moderation
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review (customer_id, order_id, item_id),   -- one review per dish per order
  FOREIGN KEY (customer_id) REFERENCES customer(customer_id),
  FOREIGN KEY (item_id)     REFERENCES menu_item(item_id),
  FOREIGN KEY (order_id)    REFERENCES orders(order_id)
) ENGINE=InnoDB;

CREATE TABLE notification (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id     INT NOT NULL,
  type            ENUM('order','payment','status','schedule','stock','group','review') NOT NULL,
  message         VARCHAR(255) NOT NULL,
  link            VARCHAR(100) NOT NULL DEFAULT 'index.php',
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customer(customer_id) ON DELETE CASCADE,
  INDEX idx_notif_customer (customer_id, is_read, created_at)
) ENGINE=InnoDB;

-- ---------- Starting data (matches the dishes used in the frontend pages) ----------
INSERT INTO menu_item (item_id, name, description, category, price, stock_quantity, is_available, is_veg, is_featured) VALUES
(1,  'Veg Hakka Noodles',  'Wok-tossed noodles, spring onion, crunchy peppers.',   'Chinese',  180, 50, 1, 1, 1),
(2,  'Paneer Manchurian',  'Pan-fried paneer cubes in a tangy Manchurian sauce.',  'Chinese',  210, 50, 1, 1, 1),
(3,  'Veg Fried Rice',     'Classic wok-fried rice with mixed vegetables.',        'Chinese',  160, 50, 1, 1, 0),
(4,  'Dal Makhani',        'Slow-cooked black lentils, cream, smoked butter.',     'Punjabi',  190, 50, 1, 1, 0),
(5,  'Butter Naan',        'Tandoor-fresh, brushed with ghee.',                    'Breads',    55, 100, 1, 1, 0),
(6,  'Chilli Chicken Dry', 'Crisp-fried chicken, soy-garlic glaze, Schezwan heat.','Chinese',  240, 40, 1, 0, 1),
(7,  'Chicken Manchurian', 'Juicy chicken chunks in a bold Manchurian gravy.',     'Chinese',  230, 40, 1, 0, 0),
(8,  'Butter Chicken',     'Tandoori chicken in a rich tomato-butter gravy.',      'Punjabi',  260, 40, 1, 0, 0),
(9,  'Chicken Momos',      'Steamed dumplings with a sesame-chilli dip.',          'Starters', 160, 0,  0, 0, 0),
(10, 'Veg Spring Rolls',   'Crispy rolls filled with cabbage, carrot and noodles.','Starters', 140, 50, 1, 1, 0);

INSERT INTO coupon (code, discount_type, discount_value, min_order_amount, expiry_date, is_active) VALUES
('WELCOME10', 'percent', 10, 0,   NULL, 1),
('PAWAN20',   'percent', 20, 300, NULL, 1);