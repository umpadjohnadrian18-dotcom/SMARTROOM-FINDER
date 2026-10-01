-- Smart Room Finder database (MySQL / MariaDB)
-- AwardSpace: create the database in the control panel, open phpMyAdmin, select it, then Import this file.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  floor TINYINT NOT NULL DEFAULT 2,
  type ENUM('classroom','laboratory') NOT NULL DEFAULT 'classroom',
  capacity INT NOT NULL DEFAULT 40,
  equipment VARCHAR(255) NOT NULL DEFAULT '',
  map_x DECIMAL(5,1) NOT NULL DEFAULT 50,  -- position on the map in % (0-100)
  map_y DECIMAL(5,1) NOT NULL DEFAULT 50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  subject VARCHAR(120) NOT NULL DEFAULT '',
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO rooms (code,name,floor,type,capacity,equipment,map_x,map_y) VALUES
('200','Room 200',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',8.0,30),
('201','Room 201',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',15.2,30),
('202','Room 202',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',22.4,30),
('203','Room 203',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',29.6,30),
('204','Room 204',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',36.8,30),
('205','Room 205',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',44.0,30),
('206','Room 206',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',51.2,30),
('207','Room 207',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',58.4,30),
('208','Room 208',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',65.6,30),
('209','Room 209',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',72.8,30),
('210','Room 210',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',80.0,30),
('211','Room 211',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',87.2,30),
('212','Room 212',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',8.0,70),
('213','Room 213',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',15.2,70),
('214','Room 214',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',22.4,70),
('215','Room 215',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',29.6,70),
('216','Room 216',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',36.8,70),
('217','Room 217',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',44.0,70),
('218','Room 218',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',51.2,70),
('219','Room 219',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',58.4,70),
('220','Room 220',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',65.6,70),
('221','Room 221',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',72.8,70),
('222','Room 222',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',80.0,70),
('223','Room 223',2,'classroom',40,'Projector, Whiteboard, Air-conditioning',87.2,70),
('CL1','Computer Laboratory 1',2,'laboratory',40,'Computers, Projector, Air-conditioning',15,10),
('CL2','Computer Laboratory 2',2,'laboratory',40,'Computers, Projector, Air-conditioning',29,10),
('CL3','Computer Laboratory 3',2,'laboratory',40,'Computers, Projector, Air-conditioning',43,10),
('CL4','Computer Laboratory 4',2,'laboratory',40,'Computers, Projector, Air-conditioning',57,10),
('CL5','Computer Laboratory 5',2,'laboratory',40,'Computers, Projector, Air-conditioning',71,10),
('CL6','Computer Laboratory 6',2,'laboratory',40,'Computers, Projector, Air-conditioning',85,10);

-- Sample schedule entries (edit or delete from the main page as admin)
INSERT INTO schedules (room_id,day,start_time,end_time,subject) VALUES
(1,'Monday','08:00','10:00','Math 101'),
(1,'Monday','13:00','15:00','English 102'),
(2,'Monday','09:00','12:00','History 101'),
(25,'Monday','08:00','11:00','Programming 1'),
(26,'Monday','10:00','13:00','Networking'),
(25,'Wednesday','13:00','16:00','Web Development');

-- The first admin account is created in the browser at /admin/login.php (shown only while no admin exists).

-- ===== v2: student accounts + room requests =====
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_no VARCHAR(20) NOT NULL UNIQUE,
  full_name VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS room_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  room_id INT NOT NULL,
  student_no VARCHAR(20) NOT NULL,
  course VARCHAR(60) NOT NULL,
  year_level VARCHAR(20) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  req_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  INDEX (room_id, req_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
