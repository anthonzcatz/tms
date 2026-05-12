-- Add default_dashboard column to user_roles table
ALTER TABLE user_roles ADD COLUMN default_dashboard VARCHAR(255) DEFAULT '/admin/dashboard/analytics' AFTER role_description;

-- Update existing roles with default dashboard
UPDATE user_roles SET default_dashboard = '/admin/dashboard/analytics' WHERE default_dashboard IS NULL;
