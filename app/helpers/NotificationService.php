<?php
/**
 * NotificationService
 * Flexible notification service for TMS
 * Supports multiple channels, templates, and real-time delivery
 */

class NotificationService {
    
    /**
     * Create a notification
     * 
     * @param array $data Notification data
     * @return int|false Notification ID or false on failure
     */
    public static function create(array $data) {
        $defaults = [
            'user_id' => null,
            'type' => 'system',
            'title' => '',
            'message' => '',
            'data' => null,
            'icon' => null,
            'color' => 'info',
            'link' => null,
            'is_read' => false,
            'priority' => 'medium',
            'real_time' => false,
            'channels' => json_encode(['in-app'])
        ];
        
        $notification = array_merge($defaults, $data);
        
        // Apply template if type exists
        if (!empty($notification['type'])) {
            $template = self::getTemplate($notification['type']);
            if ($template) {
                $notification = self::applyTemplate($notification, $template, $data['data'] ?? []);
            }
        }
        
        // Apply user preferences if user_id is set
        if (!empty($notification['user_id'])) {
            $preferences = self::getUserPreferences($notification['user_id'], $notification['type']);
            if ($preferences) {
                $notification['channels'] = $preferences['channels'] ?? $notification['channels'];
                if (!$preferences['enabled']) {
                    return false; // User has disabled this notification type
                }
            }
        }
        
        // Insert into database
        $sql = "INSERT INTO notifications (
            user_id, type, title, message, data, icon, color, link, 
            is_read, priority, real_time, channels
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $notification['user_id'],
            $notification['type'],
            $notification['title'],
            $notification['message'],
            $notification['data'] ? json_encode($notification['data']) : null,
            $notification['icon'],
            $notification['color'],
            $notification['link'],
            $notification['is_read'] ? 1 : 0,
            $notification['priority'],
            $notification['real_time'] ? 1 : 0,
            is_string($notification['channels']) ? $notification['channels'] : json_encode($notification['channels'])
        ];
        
        try {
            Database::execute($sql, $params);
            return (int)Database::lastInsertId();
        } catch (Exception $e) {
            error_log("Notification creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notifications for a user
     * 
     * @param int|null $userId User ID (null for system-wide)
     * @param array $filters Optional filters (type, is_read, priority, limit, offset)
     * @return array Notifications
     */
    public static function getNotifications($userId = null, array $filters = []) {
        $sql = "SELECT * FROM notifications WHERE 1=1";
        $params = [];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        if (!empty($filters['type'])) {
            $sql .= " AND type = ?";
            $params[] = $filters['type'];
        }
        
        if (isset($filters['is_read'])) {
            $sql .= " AND is_read = ?";
            $params[] = $filters['is_read'] ? 1 : 0;
        }
        
        if (!empty($filters['priority'])) {
            $sql .= " AND priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['real_time'])) {
            $sql .= " AND real_time = ?";
            $params[] = $filters['real_time'] ? 1 : 0;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int)$filters['offset'];
            }
        }
        
        try {
            $notifications = Database::fetchAll($sql, $params);
            
            // Parse JSON fields
            foreach ($notifications as &$notification) {
                $notification['data'] = $notification['data'] ? json_decode($notification['data'], true) : null;
                $notification['channels'] = $notification['channels'] ? json_decode($notification['channels'], true) : [];
                $notification['is_read'] = (bool)$notification['is_read'];
                $notification['real_time'] = (bool)$notification['real_time'];
            }
            
            return $notifications;
        } catch (Exception $e) {
            error_log("Get notifications failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unread count for a user
     * 
     * @param int|null $userId User ID (null for system-wide)
     * @return int Unread count
     */
    public static function getUnreadCount($userId = null) {
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE is_read = 0";
        $params = [];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        try {
            $result = Database::fetch($sql, $params);
            return (int)$result['count'];
        } catch (Exception $e) {
            error_log("Get unread count failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Mark notification as read
     * 
     * @param int $notificationId Notification ID
     * @return bool Success
     */
    public static function markAsRead($notificationId) {
        $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ?";
        
        try {
            Database::execute($sql, [$notificationId]);
            return true;
        } catch (Exception $e) {
            error_log("Mark as read failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param int|null $userId User ID (null for system-wide)
     * @return bool Success
     */
    public static function markAllAsRead($userId = null) {
        $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0";
        $params = [];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        try {
            Database::execute($sql, $params);
            return true;
        } catch (Exception $e) {
            error_log("Mark all as read failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete notification
     * 
     * @param int $notificationId Notification ID
     * @return bool Success
     */
    public static function delete($notificationId) {
        $sql = "DELETE FROM notifications WHERE notification_id = ?";
        
        try {
            Database::execute($sql, [$notificationId]);
            return true;
        } catch (Exception $e) {
            error_log("Delete notification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notification template
     * 
     * @param string $type Notification type
     * @return array|null Template data
     */
    private static function getTemplate($type) {
        $sql = "SELECT * FROM notification_templates WHERE type = ? AND enabled = 1";
        
        try {
            $template = Database::fetch($sql, [$type]);
            if ($template) {
                $template['default_channels'] = $template['default_channels'] ? json_decode($template['default_channels'], true) : [];
            }
            return $template;
        } catch (Exception $e) {
            error_log("Get template failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Apply template to notification
     * 
     * @param array $notification Notification data
     * @param array $template Template data
     * @param array $data Data for placeholder replacement
     * @return array Updated notification
     */
    private static function applyTemplate(array $notification, array $template, array $data) {
        // Use template values if not already set
        if (empty($notification['title']) && !empty($template['title_template'])) {
            $notification['title'] = self::replacePlaceholders($template['title_template'], $data);
        }
        
        if (empty($notification['message']) && !empty($template['message_template'])) {
            $notification['message'] = self::replacePlaceholders($template['message_template'], $data);
        }
        
        if (empty($notification['icon']) && !empty($template['default_icon'])) {
            $notification['icon'] = $template['default_icon'];
        }
        
        if (empty($notification['color']) && !empty($template['default_color'])) {
            $notification['color'] = $template['default_color'];
        }
        
        if (empty($notification['priority']) && !empty($template['default_priority'])) {
            $notification['priority'] = $template['default_priority'];
        }
        
        if (empty($notification['real_time']) && isset($template['default_real_time'])) {
            $notification['real_time'] = $template['default_real_time'];
        }
        
        if (empty($notification['channels']) && !empty($template['default_channels'])) {
            $notification['channels'] = json_encode($template['default_channels']);
        }
        
        if (empty($notification['link']) && !empty($template['default_link'])) {
            $notification['link'] = $template['default_link'];
        }
        
        return $notification;
    }
    
    /**
     * Replace placeholders in template
     * 
     * @param string $template Template string
     * @param array $data Data for replacement
     * @return string Replaced string
     */
    private static function replacePlaceholders($template, array $data) {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }
    
    /**
     * Get user notification preferences
     * 
     * @param int $userId User ID
     * @param string $type Notification type
     * @return array|null Preferences
     */
    private static function getUserPreferences($userId, $type) {
        // Check for specific type preference
        $sql = "SELECT * FROM notification_preferences WHERE user_id = ? AND type = ?";
        $params = [$userId, $type];
        
        try {
            $preference = Database::fetch($sql, $params);
            if ($preference) {
                $preference['channels'] = $preference['channels'] ? json_decode($preference['channels'], true) : [];
                $preference['enabled'] = (bool)$preference['enabled'];
                return $preference;
            }
            
            // Check for "all" preference
            $sql = "SELECT * FROM notification_preferences WHERE user_id = ? AND type = 'all'";
            $preference = Database::fetch($sql, [$userId]);
            if ($preference) {
                $preference['channels'] = $preference['channels'] ? json_decode($preference['channels'], true) : [];
                $preference['enabled'] = (bool)$preference['enabled'];
                return $preference;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Get user preferences failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create notification from template
     * Convenience method for common notification types
     * 
     * @param string $type Notification type
     * @param int|null $userId User ID
     * @param array $data Data for template placeholders
     * @return int|false Notification ID or false on failure
     */
    public static function createFromTemplate($type, $userId = null, array $data = []) {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'data' => $data
        ]);
    }
}
