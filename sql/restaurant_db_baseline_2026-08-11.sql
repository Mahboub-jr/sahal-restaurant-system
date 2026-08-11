-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 11, 2026 at 11:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `restaurant_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('Present','Absent','Leave') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `date`, `status`) VALUES
(1, 2, '2025-06-12', 'Present'),
(2, 1, '2025-06-12', 'Absent'),
(3, 4, '2025-06-24', 'Present');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(4, 'Macmacaan', 'Somali sweets and desserts like xalwo, buskud, and fruit treats.', '2025-06-05 13:20:29'),
(5, 'Hot Drink', 'Warm drinks such as Somali tea, coffee, and herbal infusions.', '2025-06-05 13:21:33'),
(6, 'Cold Drink', 'Refreshing beverages like juices, smoothies, and soft drinks.', '2025-06-05 13:22:19'),
(7, 'Fast Food', 'Quick bites including burgers, fries, sandwiches, and sambuus.', '2025-06-05 13:23:47'),
(8, 'Vegetables', 'Fresh or cooked veggie dishes, sides, and salads.', '2025-06-05 13:25:02'),
(9, 'Meat (Cooked, Fried, and Grilled)', 'Variety of meat dishes prepared in Somali style — tender, crispy, or smoky.', '2025-06-05 13:26:19'),
(10, 'Dinner', 'Hearty evening meals featuring grilled, fried, or cooked options.\r\n\r\n', '2025-06-05 13:27:08'),
(11, 'Lunch', 'Midday meals with rice, pasta, meat, and Somali stews.', '2025-06-05 13:27:38'),
(12, 'Breakfast', 'Morning meals including traditional Somali breads and light dishes.\r\n\r\n', '2025-06-05 13:28:03');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `address`, `created_at`) VALUES
(1, 'Nabil Mahboub', '0619835381', 'nabilmahboubabdulkadir@gmail.com', 'Hilwa', '2025-06-09 22:40:07'),
(2, 'Muhammmad Mahboub', '08068481206', 'Muhammadmahboubabdulkadir@gmail.com', 'Kano', '2025-06-10 23:51:06'),
(3, 'Khadija Mahboub', '09876543234', 'Khadijamahboub@gmail.com', 'Kano', '2025-06-11 18:06:31'),
(4, 'Faisal Abdullahi', '123456', 'Faisal@gmail.com', 'Deynile', '2025-06-24 15:01:03'),
(5, 'Raxmaa', '12345', 'Raxma@gmail.com', 'Banaadir', '2025-06-24 20:56:46');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `join_date` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `position`, `phone`, `email`, `status`, `join_date`) VALUES
(1, 'Ilyaas', 'waiter', '12345', 'Ilyaas@gmail.com', 'Active', '2025-06-12'),
(2, 'Haaji', 'cheif', '111222', 'Haaji@gmail.com', 'Inactive', '2025-06-12'),
(3, 'Mashmash', 'waiter', '111', 'admin@example.com', 'Inactive', '2025-06-12'),
(4, 'Abdikarin Mahad', 'cheif', '1234456', 'Abdi@gmail.com', 'Active', '2025-06-24');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `food_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `name`, `category_id`, `price`, `description`, `food_image`, `created_at`) VALUES
(2, 'bariis', 1, 5.00, 'healthy', '1749053426_Bariis.webp', '2025-06-04 16:10:26'),
(3, 'Xalwo', 4, 1.00, 'Sweet, spiced jelly-like dessert made with sugar, cornstarch, and ghee.', '1749133201_Xalwo.jpg', '2025-06-05 14:20:01'),
(4, 'Buskud', 4, 0.50, 'Crunchy homemade biscuits flavored with cardamom or vanilla.\r\n', '1749133257_Buskud.jpg', '2025-06-05 14:20:57'),
(6, 'Lows iyo Sisinta', 4, 0.75, 'Caramelized brittle made from roasted peanuts and sesame seeds.\r\n', '1749133380_Lows iyo Sisinta.jpg', '2025-06-05 14:23:00'),
(7, 'Mashmash', 4, 0.75, 'Sweet fried dough balls, soft inside, crisp outside', '1749133489_Mashmash.jpg', '2025-06-05 14:24:49'),
(9, 'Sambuus Macaan', 4, 0.75, 'Fried pastry filled with sweetened coconut or dates.\r\n', '1749133624_Sweet.jpg', '2025-06-05 14:27:04'),
(10, 'Dhuxulo', 4, 0.50, 'Tangy-sweet tamarind balls mixed with sugar and mild spice.', '1749133690_Dhuxulo.jpg', '2025-06-05 14:28:10'),
(11, 'Fruit Salad', 4, 1.50, 'Fresh seasonal fruit mix, sometimes with condensed milk.\r\n', '1749133750_Fruit salad.jpg', '2025-06-05 14:29:10'),
(12, 'Malawax Macaan', 4, 1.00, 'Soft pancake topped with sugar, honey, or banana.\r\n', '1749133810_Malawax macan.jpg', '2025-06-05 14:30:10'),
(13, 'Timir', 4, 0.50, 'Naturally sweet dried dates, often served with tea.\r\n', '1749133855_Tamir.jpg', '2025-06-05 14:30:55'),
(14, 'Canjeero Macaan', 4, 1.00, 'Fermented pancake sweetened with sugar or honey.\r\n', '1749133933_Canjeero maacan.jpg', '2025-06-05 14:32:13'),
(15, 'Burger', 7, 2.00, 'Grilled beef or chicken patty in a bun with fresh toppings and sauce.', '1749135961_Burger.jpg', '2025-06-05 15:06:01'),
(16, 'Fries (Chips)', 7, 0.75, 'Golden-fried potato sticks served with ketchup or chili sauce.\r\n', '1749136053_Chips.jpg', '2025-06-05 15:07:33'),
(17, 'Hot Dog', 7, 1.00, 'Juicy sausage in a soft bun with ketchup and mustard.', '1749136128_Hot Dogjpg.jpg', '2025-06-05 15:08:48'),
(18, 'Pizza', 7, 2.50, 'Grilled spiced meat wrapped in flatbread with salad and garlic sauce.\r\n', '1749136185_Pizza.jpg', '2025-06-05 15:09:45'),
(19, 'Sandwich', 7, 1.00, 'Bread filled with meat, egg, or vegetables, toasted or fresh.', '1749137845_Sandwichjpg.jpg', '2025-06-05 15:37:25'),
(20, 'Sambuus', 7, 0.50, 'Crispy triangle pastry stuffed with spiced meat or vegetables.', '1749137903_Sambuusjpg.jpg', '2025-06-05 15:38:23'),
(22, 'Fried Chicken	', 7, 1.50, 'Crispy and juicy seasoned chicken pieces.\r\n', '1749137999_Chicken.jpg', '2025-06-05 15:39:59'),
(23, 'Chapati Roll', 7, 1.25, 'Rolled flatbread filled with chicken, egg, or beef and sauce.', '1749138046_imagejpg.jpg', '2025-06-05 15:40:46'),
(24, 'Chicken & Chips Box', 7, 2.50, 'Fried chicken served with a portion of fries.\r\n', '1749138092_Chicken & Chips Boxjpg.jpg', '2025-06-05 15:41:32'),
(25, 'Cambuulo', 10, 3.00, 'Galay iyo digir', '1750788414_Cambuullo.jpeg', '2025-06-24 18:06:54');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `order_type` enum('Dine-In','Takeaway') NOT NULL,
  `items` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Preparing','Ready','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_name`, `order_type`, `items`, `total_amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Abdullahi Mohamed', 'Dine-In', '[{\"name\":\"shawarma iyo Bariis iyo shax\",\"price\":65}]', 8.50, 'Cancelled', '2025-06-02 22:18:16', '2025-06-24 21:00:49'),
(2, 'Hodan Ali', 'Takeaway', '[{\"name\":\"Rooti iyo Maraq\",\"price\":4}]', 4.00, 'Pending', '2025-06-02 22:18:16', '2025-06-11 18:04:37'),
(3, 'Mohamed Osman', 'Dine-In', '[{\"name\":\"Grilled fish\",\"price\":5}]', 9.75, 'Ready', '2025-06-02 22:18:16', '2025-06-11 18:02:02'),
(4, 'Fartun Ismail', 'Takeaway', 'Sambusa x3, Tea', 4.25, 'Completed', '2025-06-02 22:18:16', '2025-06-24 14:33:38'),
(5, 'Khalid Abdi', 'Dine-In', 'Goat Meat with Canjeero, Milk', 10.00, 'Cancelled', '2025-06-02 22:18:16', '2025-06-11 18:02:30'),
(6, 'Nabil', 'Takeaway', 'shawarma iyo Bariis iyo shax\r\n', 65.00, 'Cancelled', '2025-06-03 07:16:42', '2025-06-11 18:03:07'),
(7, 'Muhammad Nabil ', 'Takeaway', '[{\"name\":\"Grilled meat \",\"price\":5}]', 5.00, 'Ready', '2025-06-03 07:18:12', '2025-06-11 18:02:48'),
(8, 'Cumar', 'Dine-In', 'Burger iyo Coke', 3.00, 'Cancelled', '2025-06-03 07:19:19', '2025-06-11 18:03:18'),
(9, 'Axmad', 'Dine-In', '[{\"name\":\"Bariis\",\"price\":1.08}]', 7.00, 'Completed', '2025-06-03 16:38:18', '2025-06-11 18:09:54'),
(10, 'Muhammad Nabil cabdi', 'Dine-In', '[]', 5.00, 'Pending', '2025-06-04 07:51:08', '2025-06-11 18:04:26'),
(11, 'Abdi', 'Takeaway', '[{\"name\":\"bariis\",\"price\":5}]', 5.00, 'Ready', '2025-06-04 16:55:16', '2025-06-11 17:50:32'),
(12, 'Cumar', 'Takeaway', '[{\"name\":\"Shawarma iyo Tea\",\"price\":6.7},{\"name\":\"Chips\",\"price\":3}]', 9.70, 'Preparing', '2025-06-04 18:07:50', '2025-06-09 14:21:22'),
(13, 'Cumar', 'Dine-In', '[]', 49.00, 'Cancelled', '2025-06-05 16:38:16', '2025-06-09 11:55:23'),
(14, 'Shariif', 'Takeaway', '[{\"name\":\"bariis\",\"price\":5},{\"name\":\"Burger\",\"price\":7}]', 12.00, 'Cancelled', '2025-06-08 03:32:26', '2025-06-09 11:55:13'),
(15, 'Mansur', 'Takeaway', 'Bariis', 12.00, 'Cancelled', '2025-06-08 04:33:23', '2025-06-11 18:01:26'),
(16, 'Sabriin', 'Takeaway', '[{\"name\":\"Camel meat\",\"price\":8}]', 8.00, 'Completed', '2025-06-08 05:18:38', '2025-06-24 15:04:51'),
(17, 'Nuur Cali', '', '[{\"name\":\"Muufo iyo Maraq\",\"price\":7}]', 7.00, 'Completed', '2025-06-08 20:10:57', '2025-06-24 15:03:13'),
(18, 'Abdi', 'Takeaway', '[{\"name\":\"bariis\",\"price\":5},{\"name\":\"Xalwo\",\"price\":1}]', 6.00, 'Pending', '2025-06-09 08:01:00', '2025-06-09 12:05:54'),
(19, 'Faisal', '', '[{\"id\":\"2\",\"name\":\"bariis\",\"price\":5}]', 5.00, 'Completed', '2025-06-24 10:59:01', '2025-06-24 15:20:06'),
(20, 'RAxma', '', '[{\"id\":\"15\",\"name\":\"Burger\",\"price\":2},{\"id\":\"2\",\"name\":\"bariis\",\"price\":5},{\"id\":\"9\",\"name\":\"Sambuus Macaan\",\"price\":0.75}]', 7.75, 'Completed', '2025-06-24 16:54:15', '2025-06-24 20:57:57');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `payment_method` enum('Cash','Card','Mobile Money') DEFAULT NULL,
  `status` enum('Paid','Pending') DEFAULT 'Paid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `customer_id`, `amount`, `payment_date`, `payment_method`, `status`) VALUES
(1, 1, 1, 50.00, '2025-06-11 12:21:00', 'Card', 'Paid'),
(2, 2, 2, 10.00, '2025-06-11 12:21:00', 'Cash', 'Pending'),
(3, 9, 3, 10.00, '2025-06-11 17:09:00', 'Mobile Money', 'Paid'),
(4, 4, 2, 45.00, '2025-06-24 13:32:00', 'Card', 'Paid'),
(5, 17, 4, 5.00, '2025-06-24 14:02:00', 'Mobile Money', 'Paid'),
(6, 16, 4, 5.00, '2025-06-24 14:04:00', 'Mobile Money', 'Paid'),
(7, 19, 4, 5.00, '2025-06-24 14:19:00', 'Cash', 'Paid'),
(8, 19, 4, 5.00, '2025-06-24 14:20:00', 'Cash', 'Paid'),
(9, 20, 5, 7.75, '2025-06-24 19:56:00', 'Cash', 'Paid');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `restaurant_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `opening_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`opening_hours`)),
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `service_charge` decimal(5,2) DEFAULT 0.00,
  `currency_symbol` varchar(10) DEFAULT '$',
  `invoice_prefix` varchar(50) DEFAULT 'INV-',
  `show_logo_on_invoice` tinyint(1) DEFAULT 1,
  `invoice_footer_note` text DEFAULT NULL,
  `theme` varchar(50) DEFAULT 'default'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `restaurant_name`, `address`, `phone`, `email`, `logo`, `opening_hours`, `tax_rate`, `service_charge`, `currency_symbol`, `invoice_prefix`, `show_logo_on_invoice`, `invoice_footer_note`, `theme`) VALUES
(3, 'Sahal Restaurant', 'Banaadir, Mogadishu, Somalia', '+252619835381', 'Sahal@gmail.com', '', '{\"Monday\":\"\",\"Tuesday\":\"\",\"Wednesday\":\"\",\"Thursday\":\"\",\"Friday\":\"\",\"Saturday\":\"\",\"Sunday\":\"\"}', 5.00, 10.00, '$', 'INV-', 1, 'Thank you for dining with us!', 'default');

-- --------------------------------------------------------

--
-- Table structure for table `tables`
--

CREATE TABLE `tables` (
  `id` int(11) NOT NULL,
  `table_number` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('Available','Reserved','Occupied') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tables`
--

INSERT INTO `tables` (`id`, `table_number`, `capacity`, `status`) VALUES
(3, 'T01', 4, 'Available'),
(4, 'T02', 4, 'Available'),
(5, 'T03', 4, 'Reserved'),
(6, 'T04', 4, 'Occupied'),
(7, 'T05', 6, 'Reserved'),
(8, 'T06', 6, 'Occupied'),
(10, 'T07', 6, 'Available'),
(11, 'T08', 8, 'Available'),
(12, 'T09', 8, 'Occupied'),
(13, 'T10', 10, 'Available'),
(14, 'T011', 10, 'Reserved');

-- --------------------------------------------------------

--
-- Table structure for table `table_bookings`
--

CREATE TABLE `table_bookings` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `table_id` int(11) DEFAULT NULL,
  `booking_time` datetime DEFAULT NULL,
  `status` enum('Booked','Seated','Cancelled') DEFAULT 'Booked'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `table_bookings`
--

INSERT INTO `table_bookings` (`id`, `customer_id`, `table_id`, `booking_time`, `status`) VALUES
(1, 1, 3, '2025-06-10 23:52:00', 'Booked'),
(2, 2, 4, '2025-06-11 00:00:00', 'Seated'),
(3, 1, 3, '2025-06-11 14:40:00', 'Seated');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'Admin', 'admin@example.com', '$2y$10$1h2lEPl6iPC15zQk2kPXv..4EQUI.ECagB22yKjfvqlRTyECTu8sO', 'admin'),
(5, 'Mahboub', 'Mahbub@gmail.com', '$2y$10$wXLK44bPDlaFRol7EXvwXeLf1uktqi6aJ91vhRxIeDdYo0vcEd8em', 'waiter'),
(6, 'Nabil', 'nabil@gmail.com', '$2y$10$eeNx0Twu9aKHuHozbYCPsetvoW54i2QT4mCUbc9tdIjDlPlUMWcHi', 'admin'),
(9, 'suuto', 'suuto@gmail.com', '$2y$10$L.UqFFsFanWmjeHoSfO2YeXQ/j6RBopIsPoRXBoCASGaPmH32CADa', 'admin'),
(10, 'Farxaan', 'Farxaan@gmail.com', '$2y$10$prQHAkPgUdXDxIu53H6tnuUsvki3XYLCg2nHJxEcIm/daIFUNhm1O', 'waiter'),
(12, 'Salma', 'salma@gmail.com', '$2y$10$.pKGVayVEgHehql66GkY0OwfNsjWA4WMcpdfYfSQSavELLZpg818K', 'admin'),
(13, 'Sayyid', 'sayyid@gmail.com', '$2y$10$9nck9XaE1.9fFtk/Sf.qdeeks8l9LmJb8feCVkIOYe/cFlk9qP8D6', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`,`date`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `table_bookings`
--
ALTER TABLE `table_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `table_id` (`table_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tables`
--
ALTER TABLE `tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `table_bookings`
--
ALTER TABLE `table_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `table_bookings`
--
ALTER TABLE `table_bookings`
  ADD CONSTRAINT `table_bookings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `table_bookings_ibfk_2` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
