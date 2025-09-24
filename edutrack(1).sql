-- phpMyAdmin SQL Dump
-- version 5.2.1-1.el8
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Сен 24 2025 г., 18:18
-- Версия сервера: 8.0.36
-- Версия PHP: 7.2.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `edutrack`
--

-- --------------------------------------------------------

--
-- Структура таблицы `courses`
--

CREATE TABLE `courses` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `teacher_id` int NOT NULL,
  `schedule` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `courses`
--

INSERT INTO `courses` (`id`, `name`, `description`, `teacher_id`, `schedule`, `created_at`) VALUES
(1, 'Программирование', 'Основы Python', 2, 'Пн, Ср 15:00', '2025-09-08 09:25:46'),
(2, 'Робототехника', 'Создание роботов', 2, 'Вт, Чт 14:00', '2025-09-08 09:25:46'),
(3, 'Искусство', 'Рисование и дизайн', 2, 'Пт 16:00', '2025-09-08 09:25:46'),
(4, 'БПЛА младшая группа', 'БПЛА', 2, 'Понедельник 15:00 - 16:20', '2025-09-08 13:20:35'),
(5, 'БПЛА', 'БПЛА', 202, 'Пн (16:00-17:00)', '2025-09-10 09:28:16'),
(6, 'БПЛА', 'БПЛА', 202, 'Пн (16:00-17:00)', '2025-09-10 09:28:37');

-- --------------------------------------------------------

--
-- Структура таблицы `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `course_id` int NOT NULL,
  `status` enum('pending','approved','rejected','replaced_pending') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `status`, `created_at`) VALUES
(1, 3, 1, 'pending', '2025-09-08 09:25:46'),
(2, 3, 2, 'approved', '2025-09-08 09:25:46'),
(3, 3, 3, 'pending', '2025-09-08 09:25:46'),
(4, 4, 1, 'approved', '2025-09-08 09:25:46');

-- --------------------------------------------------------

--
-- Структура таблицы `grades`
--

CREATE TABLE `grades` (
  `id` int NOT NULL,
  `enrollment_id` int NOT NULL,
  `date` date NOT NULL,
  `grade` enum('0','1','2') NOT NULL,
  `attendance` enum('present','absent') DEFAULT 'present',
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `grades`
--

INSERT INTO `grades` (`id`, `enrollment_id`, `date`, `grade`, `attendance`, `comment`, `created_at`) VALUES
(1, 2, '2025-09-01', '2', 'present', 'Отличная работа', '2025-09-08 09:25:46'),
(2, 4, '2025-09-01', '1', 'present', 'Нужно больше практики', '2025-09-08 09:25:46');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `class` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `full_name`, `email`, `class`, `created_at`) VALUES
(1, 'admin', '$2y$10$9UzFnUWxDBFv7hL86s0llObRo11Z.7cxIplI6qT4JcIbI8iY2nl9i', 'admin', 'Админ', 'admin@example.com', NULL, '2025-09-08 09:25:46'),
(2, 'teacher1', '$2y$10$9UzFnUWxDBFv7hL86s0llObRo11Z.7cxIplI6qT4JcIbI8iY2nl9i', 'teacher', 'Иван Иванов', 'teacher@example.com', NULL, '2025-09-08 09:25:46'),
(3, 'student1', '$2y$10$K.ExampleHashHere', 'student', 'Петров Алексей', 'student@example.com', '8А', '2025-09-08 09:25:46'),
(4, 'student2', '$2y$10$K.ExampleHashHere', 'student', 'Сидорова Анна', 'student2@example.com', '8Б', '2025-09-08 09:25:46'),
(138, 'kharitonov_va', '$2y$10$UyPx7trgjttjdYHaN19Stuynz9Nq5jYr2.4H0G7.BDUHEI9xABNia', 'student', 'Харитонов Виктор Алексеевич', 'kharitonov_va@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(139, 'maksimova_aa', '$2y$10$nU9GnS203uGq/Wc924KqW.erCk37B0xAAgiv1K9WaYUDyrOoo4Izy', 'student', 'Максимова Алёна Андреевна', 'maksimova_aa@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(140, 'danilova_vd', '$2y$10$D2Rv65wjCD4MCGnEoheYSOlVHoSZt2y1dkmv8Ft4goS5qIBGkS98S', 'student', 'Данилова Валерия Дмитриевна', 'danilova_vd@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(141, 'alferyeva_ev', '$2y$10$fim0T0gA6z/TtuFLn5HTrOdRqZM4pYR4TF3s/hBBJUM5SpoScQbnC', 'student', 'Алферьева Елизавета Валерьевна', 'alferyeva_ev@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(142, 'taran_sa', '$2y$10$SK0VKNSrwltxJYB3uDqoX.r/Sc4wKjIpAHuhsluEGzHHrIePZowVu', 'student', 'Таран Савва Александрович', 'taran_sa@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(143, 'gorev_dd', '$2y$10$Iq7L9L9pVotbfPxFBXrtEeAwKLtxXVeCFbGnKFT2dnsufhRtuzvqS', 'student', 'Горев Данил Денисович', 'gorev_dd@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(144, 'zykov_ai', '$2y$10$L9wUtLdtYxqmxXcBeMGA8OmY.7QyLhUePesUPb4wYlAfyJFhbQg5e', 'student', 'Зыков Алексей Иванович', 'zykov_ai@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(145, 'telepneva_ke', '$2y$10$X/icv5BRJolapXvx2uO.Q.kj4Mhw3Q7pTdFFkvszsd4dHm3HA0toq', 'student', 'Телепнева Кристина Евгеньевна', 'telepneva_ke@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(146, 'kremen_aa1', '$2y$10$LvG60q4UUI.DuOyzEUX/feC42GxLlDXox0GScOVATLbCFYFXCFk.i', 'student', 'Кремен Артём Александрович', 'kremen_aa1@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(147, 'nerusheva_mr', '$2y$10$c/IgqfCjjhMyFTPx0EfDWeJX0bVrpssFxNEgW4ZeUa4Nsnsn5CBw2', 'student', 'Нерушева Мария Ромиановна', 'nerusheva_mr@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(148, 'alimov_se', '$2y$10$H7/YtDKE5guivm5G8ydpVOci.T4AffX7vLhIR6L/hADHk.gTU50Ny', 'student', 'Алимов Степан Евгеньевич', 'alimov_se@efutrack.com', '7ЛА', '2025-09-09 11:27:09'),
(149, 'sasalentina_mv', '$2y$10$jQYcdebDoXADR0T8h/Ex8./PmjOnQbpIPNry.D0kp0fXfWv2yDgFS', 'student', 'Сасалетина Милана Владимировна', 'sasalentina_mv@efutrack.com', '8ЛБ', '2025-09-09 11:27:09'),
(150, 'mergasova_kk', '$2y$10$jk6IwaM3WWQDjAjZyfxfk.4usfOeJHjGJMUZNEZWZV0p0RGvG6zHi', 'student', 'Мергасова Карина Кирилловна', 'mergasova_kk@efutrack.com', '8ЛБ', '2025-09-09 11:27:09'),
(151, 'rybak_ts', '$2y$10$TIziMmGBUp01jJYqgF4kH.z.A0JF.7rYQthmPKh8cJvV7z.wavsxC', 'student', 'Рыбак Тарас Сергеевич', 'rybak_ts@efutrack.com', '8ЛБ', '2025-09-09 11:27:09'),
(152, 'zobyan_va', '$2y$10$G3WeW/NGjl1yxtsGiDxn9.iOxlBWRykDNYwj7EsEx4mYABUylCrbC', 'student', 'Зобян Владимир Араратович', 'zobyan_va@efutrack.com', '8ЛБ', '2025-09-09 11:27:09'),
(153, 'zheleznov_es', '$2y$10$8Lt6D/.OfisGhSfhm/3kZuzDDi6laCrDH5ArA8W.3tLf.IE6LdWLG', 'student', 'Железнов Егор Сергеевич', 'zheleznov_es@efutrack.com', '8ЛБ', '2025-09-09 11:27:09'),
(154, 'selivanov_mm', '$2y$10$lpowdPNwnVZFh4xaxikjROH5xfzFnYAtvZa2vFmqylmPPEoD0ii4m', 'student', 'Селиванов Матвей Михайлович', 'selivanov_mm@efutrack.com', '8ЛБ', '2025-09-09 11:27:10'),
(155, 'startsev_md', '$2y$10$zNc0EslN0uXwfvX9fNLu7eaFJDU243uL1DqpNV0/fthJ5Qw.I6oz2', 'student', 'Старцев Максим Денисович', 'startsev_md@efutrack.com', '8ЛБ', '2025-09-09 11:27:10'),
(156, 'bashlay_aa', '$2y$10$oMx9nxEePTCPPq1Zyouo3.2xxjPQ3X4JJidIcwjq7D.gg7KP2puJW', 'student', 'Башлай Альберт Александрович', 'bashlay_aa@efutrack.com', '8ЛБ', '2025-09-09 11:27:10'),
(157, 'kuklenko_tp', '$2y$10$gIvck/neDgi30aJZeEaHseLyD7Vxx4b6tLcXNAY/SuvCxNFq7ZS96', 'student', 'Кукленко Тимофей Павлович', 'kuklenko_tp@efutrack.com', '8ЛБ', '2025-09-09 11:27:10'),
(158, 'makhotin_av', '$2y$10$Sq812JSkkeA/eKc4yLFJM.VrFt4AREF9Omu3McaUnctNzdpGTfmUK', 'student', 'Махотин Артём Викторович', 'makhotin_av@efutrack.com', '8ЛБ', '2025-09-09 11:27:10'),
(159, 'kaverin_ii', '$2y$10$PDiGixHOTTjjMSvKvNHFG.E4yAuBEtpe7XVUi1MLl5FObaYZGclbG', 'student', 'Каверин Илья Игоревич', 'kaverin_ii@efutrack.com', '8ЛБ', '2025-09-09 11:27:10'),
(160, 'shishkarenko_vd', '$2y$10$cXnV1OuFO98cen0olU9u5.Jw2frg7FyT0n/Lil2R0hWU0ok0lfnLm', 'student', 'Шишкаренко Виктор Дмитриевич', 'shishkarenko_vd@efutrack.com', '8ЛБ', '2025-09-09 11:27:10'),
(161, 'kremen_ka2', '$2y$10$7UqDqqeekuW9FQfX8Dk9qeTMbLk715m7Ph3xjGuj70M0bil8.oRz6', 'student', 'Кремен Кирилл Александрович', 'kremen_ka2@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(162, 'tagiltseva_ye', '$2y$10$YX.b7tBRPPTkKIsxJSTe0eAHwbz0mJu4qxeyaQqApu.IJhpKJXu96', 'student', 'Тагильцева Юлиана Евгеньевна', 'tagiltseva_ye@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(163, 'vershinin_iv', '$2y$10$T9K4hE0Wm2sgDm5qHm0CGujWbhvrVDVPe8TBKmj8f6BItPT/D8ufm', 'student', 'Вершинин Иван Валерьевич', 'vershinin_iv@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(164, 'gvozdev_mm', '$2y$10$Hs35.2XBAK.9g/hu5OOTs.U5XL6mbaZrBMCWCnzc6lxeugdH2uGv.', 'student', 'Гвоздев Матвей Максимович', 'gvozdev_mm@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(165, 'matrosov_rm', '$2y$10$NLJHw3LnQngUGNU39xYA2e1hVNl10B2xvtjlm0OmLLKzIV7yuUl8a', 'student', 'Матросов Роман Матвеевич', 'matrosov_rm@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(166, 'tereshchenko_sa', '$2y$10$tNPhM9uzKBEyMlucqeH1/.cmLrEqWnfhbX6xb3MzPaqg5rg2hQnie', 'student', 'Терещенко София Андреевна', 'tereshchenko_sa@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(167, 'leysle_ae', '$2y$10$7jGJmkaFL4CmDAiF5TSe6uh.7jwdwaiUgrNaot50kuWUyP7bxv/K2', 'student', 'Лейсле Алиса Евгеньевна', 'leysle_ae@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(168, 'nikitin_kn', '$2y$10$XCN4Ee1try.GGPz3b2qEIeavKmKqMdn3HWmzgfE/bvVcL1jEO9dI2', 'student', 'Никитин Кирилл Никитич', 'nikitin_kn@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(169, 'malyanova_aa', '$2y$10$dNjZwc82zcYcD/KZo4mcROBgR6m4PBJAw3WO4iRFf2YYJMcsprIJG', 'student', 'Малянова Анастасия Антоновна', 'malyanova_aa@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(170, 'levich_es', '$2y$10$iIYQKh09IVTk4Ayzl9gpxueYKck/J6SA6dZspDyM318qyQatQylTe', 'student', 'Левич Екатерина Сергеевна', 'levich_es@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(171, 'slaboshpitzkaya_ve', '$2y$10$GYcXM3XjUk/ARN/ucKt6ZuW.a040hkKkI/OGFLSXfE6tJIaxS7gpi', 'student', 'Слабошпицкая Вероника Евгеньевна', 'slaboshpitzkaya_ve@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(172, 'kalichkin_lv', '$2y$10$TTH7ftbGCEXGe932kzT9p.gKAPbBh5./.jf3ETDIz92nc8xsD9bzi', 'student', 'Каличкин Лев Владимирович', 'kalichkin_lv@efutrack.com', '9ЛБ', '2025-09-09 11:27:10'),
(173, 'frolov_sv', '$2y$10$EyfAzFgnoP3kIvj/8Bupt.gICRwPy/prQxjGFTnvw5cJSoj.Kh8ta', 'student', 'Фролов Сергей Владимирович', 'frolov_sv@efutrack.com', '10ЛА', '2025-09-09 11:27:10'),
(174, 'isakov_ad', '$2y$10$4XLJCFTMXUMOAaEVYQPGtesclFW5yPX8UjT5r5FiejA3nvEGT229G', 'student', 'Исаков Алексей Дмитриевич', 'isakov_ad@efutrack.com', '10ЛА', '2025-09-09 11:27:10'),
(175, 'shutova_ma', '$2y$10$NUPZVi8AU9WsPg7FOemXJu30qmvkSHOeuWHQb3bCZ4HJCwUtouHca', 'student', 'Шутова Милена Александровна', 'shutova_ma@efutrack.com', '10ЛА', '2025-09-09 11:27:10'),
(176, 'gerasimenko_la', '$2y$10$xq7cub1BwEwVIM.H3.KNdOaqRvP3V6MfFftCGJB8gMn0x0JQYKfe.', 'student', 'Герасименко Лев Анатольевич', 'gerasimenko_la@efutrack.com', '10ЛА', '2025-09-09 11:27:11'),
(177, 'averkov_ia', '$2y$10$uEz53A5muh0ZIdxY8ROmWOLcUlQjQVAxZN3anBPy2UHxJPsDqThaS', 'student', 'Аверков Иван Александрович', 'averkov_ia@efutrack.com', '10ЛА', '2025-09-09 11:27:11'),
(178, 'letyagin_as', '$2y$10$q9z7OuvZgtfrGdO27AxJxOw4n9w2py5uvK71QdxSTDTCZxQlmo76e', 'student', 'Летягин Александр Сергеевич', 'letyagin_as@efutrack.com', '10ЛА', '2025-09-09 11:27:11'),
(179, 'kravchenko_av', '$2y$10$7u7F62vB9NEA/4o2mp0nS.QRwF2WI3OZRUwEtY0WfLAeDOJbsGXya', 'student', 'Кравченко Александра Валериевна', 'kravchenko_av@efutrack.com', '10ЛБ', '2025-09-09 11:27:11'),
(180, 'kalugina_ab', '$2y$10$eb2bgunwJFGx7JLXDK7PBeOMwYPjeuthmN9UMIT.Uc7aoY.fxi6aa', 'student', 'Калугина Анастасия Борисовна', 'kalugina_ab@efutrack.com', '10ЛБ', '2025-09-09 11:27:11'),
(181, 'kharin_ai', '$2y$10$Eudv5zevwWkI2oT10lDw1e2pL3bgGqhDJP3EWUrdl240CA84MApI6', 'student', 'Харин Александр Игоревич', 'kharin_ai@efutrack.com', '10ЛБ', '2025-09-09 11:27:11'),
(182, 'tarasenkova_aa', '$2y$10$V2fLw.uKoT0smbpaCO0SeuN73bYePsNgkkZI1KPnyTAJkpo2ktswS', 'student', 'Тарасенкова Анастасия Антоновна', 'tarasenkova_aa@efutrack.com', '10ЛБ', '2025-09-09 11:27:11'),
(183, 'korolkova_av', '$2y$10$tlSyC1PIJPsYgRJudn5.V./QIyLHhlQ7AKj7ZmwCj024CJwo3a98G', 'student', 'Королькова Анна Владимировна', 'korolkova_av@efutrack.com', '10ЛБ', '2025-09-09 11:27:11'),
(184, 'protasova_ek', '$2y$10$HuD4./9zto4TIv7nRiHLkOHABbOdBZSOZq8PuADENf1LjCyyiw39S', 'student', 'Протасова Елизавета Константиновна', 'protasova_ek@efutrack.com', '10ЛБ', '2025-09-09 11:27:11'),
(185, 'glazkin_gv', '$2y$10$M4gQQ0RmJ60EOMo4XyjcguSsqY.uO7IACM3Udwd4QGvkyVsav0r7G', 'student', 'Глазкин Геннадий Витальевич', 'glazkin_gv@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(186, 'belyaeva_ey', '$2y$10$jgAUfaby3GNalJjCb7h9ru91GR/Dx7IQDu0bgxEHPDJEbf8xVDBKG', 'student', 'Беляева Екатерина Юрьевна', 'belyaeva_ey@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(187, 'kazina_pa', '$2y$10$lQQoeg42KJtlckm/cWQ8mumz2Qxt9pURrSVHlErKkGcPDFpagGYRa', 'student', 'Казина Полина Анатольевна', 'kazina_pa@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(188, 'starovoytov_ad', '$2y$10$Pdk1X2YPBE8NDFntb8hm5.lppIUG2GX2vGJtNIxE5UBNSR2X9sW06', 'student', 'Старовойтов Александр Дмитриевич', 'starovoytov_ad@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(189, 'vinogradova_ad', '$2y$10$.w4rqBVcwQxdoneXgaq79OmMyLM5Px5nCrHnKRqwsBqy3hQggYDxO', 'student', 'Виноградова Александра Денисовна', 'vinogradova_ad@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(190, 'stasyukov_es', '$2y$10$gm.km.CXFvzdljoO1xsOc.jQcurLSO.GcJli4I7Z2NDIwFfg6szyu', 'student', 'Стасюков Егор Станиславович', 'stasyukov_es@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(191, 'yuryeva_ap', '$2y$10$WIKWb/cMez6a1nF4MVXAsuhs5p2On0GR/xk4YNv7WpKZ5Fq.uguzy', 'student', 'Юрьева Анна Павловна', 'yuryeva_ap@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(192, 'paramonova_sv', '$2y$10$o0.ZjIn3sk06JMCOZGEt2ea1Iv.y7fl6SVHmXPKu9rU0ipStTs.0y', 'student', 'Парамонова Софья Владимировна', 'paramonova_sv@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(193, 'karasenko_ma', '$2y$10$ZoXSiRdc3oWMBj6Fif4f9.KyU1boNH6FOU2fVObdq6Vb7RVBIS1aS', 'student', 'Карасенко Марк Александрович', 'karasenko_ma@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(194, 'koneva_vd', '$2y$10$Sq1aaRadgMvqN1/igOvveO/TzJRKJeOsko3n6PO57gNO0GQEAwACu', 'student', 'Конева Варвара Дмитриевна', 'koneva_vd@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(195, 'zhigulin_tk', '$2y$10$Ob/URAtcc6SoRAbx/k/6uey2ZzdvtOEGY5TZocXcvU7KgIZqkaotG', 'student', 'Жигулин Тимофей Константинович', 'zhigulin_tk@efutrack.com', '11ЛА', '2025-09-09 11:27:11'),
(196, 'vertinskaya_es', '$2y$10$IvO3F4YFM4OzjALZIX6b2e13JhGh4kTwV.VbfCtHyY55OyBqViMEO', 'student', 'Вертинская Екатерина Сергеевна', 'vertinskaya_es@efutrack.com', '11ЛБ', '2025-09-09 11:27:11'),
(197, 'ufimtseva_ai', '$2y$10$X2zyp0dKd1hRd94GnYr.jur9D.Rvf9FiSzf.WhRiMcfvU95MKcTYS', 'student', 'Уфимцева Алина Ивановна', 'ufimtseva_ai@efutrack.com', '11ЛБ', '2025-09-09 11:27:11'),
(198, 'guchmazova_mr', '$2y$10$D6dosfF4PZG7XFYZ29HPtun0INmnJdANIYujFnj/2uqfVZTO5oCGG', 'student', 'Гучмазова Милана Ростомовна', 'guchmazova_mr@efutrack.com', '11ЛБ', '2025-09-09 11:27:12'),
(199, 'dokukin_ee', '$2y$10$yAI0cqK7/o0oPnfynoMIWuqFuduM4WhOpO3Qc7VoLCnmbfMqEOAXK', 'student', 'Докукин Егор Евгеньевич', 'dokukin_ee@efutrack.com', '11ЛБ', '2025-09-09 11:27:12'),
(200, 'panarina_as', '$2y$10$q9MqPX8Ypxpg/f3XGc8Tp.IG2B7jCx5hwy0tOuNLD69ErHaPk6tA6', 'student', 'Панарина Анастасия Сергеевна', 'panarina_as@efutrack.com', '11ЛБ', '2025-09-09 11:27:12'),
(201, 'bulaev_zv', '$2y$10$VELKGYBbG3ruKOVwY1IANuK9NNw/lYNTdri82xnkFiblY16xwobGW', 'student', 'Булаев Захар Вячеславович', 'bulaev_zv@efutrack.com', '11ЛБ', '2025-09-09 11:27:12'),
(202, 'teacher2', '$2y$10$iZ2UYB18iQz1yBes7LzIKuFaw9vrBgbg9ZTVQIYMquDElftmzuz7e', 'teacher', 'Константин Сергеевич', 'deafaf@gmail.com', '-', '2025-09-10 09:06:12');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Индексы таблицы `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Индексы таблицы `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enrollment_id` (`enrollment_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=203;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
