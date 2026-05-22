-- Add comprehensive branch settings including operating hours
-- This allows branches to have specific operating hours for each day

-- Operating hours columns (format: HH:MM 24-hour format)
ALTER TABLE business_branches 
ADD COLUMN monday_open TIME DEFAULT '08:00:00' COMMENT 'Monday opening time' AFTER email,
ADD COLUMN monday_close TIME DEFAULT '18:00:00' COMMENT 'Monday closing time' AFTER monday_open,
ADD COLUMN monday_closed TINYINT(1) DEFAULT 0 COMMENT 'Monday is closed' AFTER monday_close,
ADD COLUMN monday_break_start TIME DEFAULT NULL COMMENT 'Monday break start time' AFTER monday_closed,
ADD COLUMN monday_break_end TIME DEFAULT NULL COMMENT 'Monday break end time' AFTER monday_break_start,

ADD COLUMN tuesday_open TIME DEFAULT '08:00:00' COMMENT 'Tuesday opening time' AFTER monday_break_end,
ADD COLUMN tuesday_close TIME DEFAULT '18:00:00' COMMENT 'Tuesday closing time' AFTER tuesday_open,
ADD COLUMN tuesday_closed TINYINT(1) DEFAULT 0 COMMENT 'Tuesday is closed' AFTER tuesday_close,
ADD COLUMN tuesday_break_start TIME DEFAULT NULL COMMENT 'Tuesday break start time' AFTER tuesday_closed,
ADD COLUMN tuesday_break_end TIME DEFAULT NULL COMMENT 'Tuesday break end time' AFTER tuesday_break_start,

ADD COLUMN wednesday_open TIME DEFAULT '08:00:00' COMMENT 'Wednesday opening time' AFTER tuesday_break_end,
ADD COLUMN wednesday_close TIME DEFAULT '18:00:00' COMMENT 'Wednesday closing time' AFTER wednesday_open,
ADD COLUMN wednesday_closed TINYINT(1) DEFAULT 0 COMMENT 'Wednesday is closed' AFTER wednesday_close,
ADD COLUMN wednesday_break_start TIME DEFAULT NULL COMMENT 'Wednesday break start time' AFTER wednesday_closed,
ADD COLUMN wednesday_break_end TIME DEFAULT NULL COMMENT 'Wednesday break end time' AFTER wednesday_break_start,

ADD COLUMN thursday_open TIME DEFAULT '08:00:00' COMMENT 'Thursday opening time' AFTER wednesday_break_end,
ADD COLUMN thursday_close TIME DEFAULT '18:00:00' COMMENT 'Thursday closing time' AFTER thursday_open,
ADD COLUMN thursday_closed TINYINT(1) DEFAULT 0 COMMENT 'Thursday is closed' AFTER thursday_close,
ADD COLUMN thursday_break_start TIME DEFAULT NULL COMMENT 'Thursday break start time' AFTER thursday_closed,
ADD COLUMN thursday_break_end TIME DEFAULT NULL COMMENT 'Thursday break end time' AFTER thursday_break_start,

ADD COLUMN friday_open TIME DEFAULT '08:00:00' COMMENT 'Friday opening time' AFTER thursday_break_end,
ADD COLUMN friday_close TIME DEFAULT '18:00:00' COMMENT 'Friday closing time' AFTER friday_open,
ADD COLUMN friday_closed TINYINT(1) DEFAULT 0 COMMENT 'Friday is closed' AFTER friday_close,
ADD COLUMN friday_break_start TIME DEFAULT NULL COMMENT 'Friday break start time' AFTER friday_closed,
ADD COLUMN friday_break_end TIME DEFAULT NULL COMMENT 'Friday break end time' AFTER friday_break_start,

ADD COLUMN saturday_open TIME DEFAULT '08:00:00' COMMENT 'Saturday opening time' AFTER friday_break_end,
ADD COLUMN saturday_close TIME DEFAULT '18:00:00' COMMENT 'Saturday closing time' AFTER saturday_open,
ADD COLUMN saturday_closed TINYINT(1) DEFAULT 0 COMMENT 'Saturday is closed' AFTER saturday_close,
ADD COLUMN saturday_break_start TIME DEFAULT NULL COMMENT 'Saturday break start time' AFTER saturday_closed,
ADD COLUMN saturday_break_end TIME DEFAULT NULL COMMENT 'Saturday break end time' AFTER saturday_break_start,

ADD COLUMN sunday_open TIME DEFAULT '08:00:00' COMMENT 'Sunday opening time' AFTER saturday_break_end,
ADD COLUMN sunday_close TIME DEFAULT '18:00:00' COMMENT 'Sunday closing time' AFTER sunday_open,
ADD COLUMN sunday_closed TINYINT(1) DEFAULT 0 COMMENT 'Sunday is closed' AFTER sunday_close,
ADD COLUMN sunday_break_start TIME DEFAULT NULL COMMENT 'Sunday break start time' AFTER sunday_closed,
ADD COLUMN sunday_break_end TIME DEFAULT NULL COMMENT 'Sunday break end time' AFTER sunday_break_start;

-- Additional branch settings
ALTER TABLE business_branches 
ADD COLUMN is_24_hours TINYINT(1) DEFAULT 0 COMMENT 'If 1, branch is open 24/7 (overrides individual day hours)' AFTER sunday_break_end,
ADD COLUMN max_capacity INT DEFAULT 100 COMMENT 'Maximum customer capacity' AFTER is_24_hours,
ADD COLUMN manager_name VARCHAR(100) DEFAULT NULL COMMENT 'Branch manager name' AFTER max_capacity,
ADD COLUMN manager_contact VARCHAR(50) DEFAULT NULL COMMENT 'Branch manager contact' AFTER manager_name,
ADD COLUMN notes TEXT DEFAULT NULL COMMENT 'Additional branch notes' AFTER manager_contact;
